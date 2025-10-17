<?php

namespace App\Services;

use App\Models\Family;
use App\Models\Member;
use App\Models\MemberContact;
use App\Models\MemberCustomField;
use App\Models\MemberCustomValue;
use App\Models\MemberProcessRun;
use App\Models\MembershipProcess;
use App\Models\Tenant;
use App\Services\AuditLogService;
use App\Services\CustomFields\CustomFieldFileService;
use App\Services\MembershipProcessService;
use App\Services\PlanEnforcementService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MemberService
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly CustomFieldFileService $customFieldFileService,
        private readonly MembershipProcessService $membershipProcessService,
        private readonly PlanEnforcementService $planEnforcementService,
        private readonly AuditLogService $auditLogService
    )
    {
    }

    public function create(array $attributes): Member
    {
        return DB::transaction(function () use ($attributes) {
            $contacts = $attributes['contacts'] ?? [];
            $families = $attributes['families'] ?? [];
            $customValues = $attributes['custom_values'] ?? [];

            $payload = Arr::except($attributes, ['contacts', 'families', 'custom_values']);
            $this->applyActor($payload, creating: true);

            $tenant = $this->resolveTenantContext($payload['tenant_id'] ?? null);

            if ($tenant) {
                $this->planEnforcementService->ensureCanUse($tenant, 'members');
            }

            $member = Member::create($payload);

            $this->syncContacts($member, $contacts);
            $this->syncFamilies($member, $families);
            $this->syncCustomValues($member, $customValues);

            $member = $member->load(['contacts', 'families']);

            if ($tenant) {
                $this->planEnforcementService->recordUsage($tenant, 'members');
            }

            $this->maybeAutoStartMembershipProcess($member);

            $this->auditLogService->record([
                'tenant_id' => $member->tenant_id,
                'user_id' => optional(Auth::user())->id,
                'action' => 'member.created',
                'auditable_type' => Member::class,
                'auditable_id' => $member->id,
                'payload' => [
                    'attributes' => Arr::only($member->toArray(), [
                        'first_name',
                        'last_name',
                        'membership_status',
                        'membership_stage',
                    ]),
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);

            return $member;
        });
    }

    public function update(Member $member, array $attributes): Member
    {
        return DB::transaction(function () use ($member, $attributes) {
            $contacts = $attributes['contacts'] ?? null;
            $families = $attributes['families'] ?? null;
            $customValues = $attributes['custom_values'] ?? null;

            $payload = Arr::except($attributes, ['contacts', 'families', 'custom_values']);
            $this->applyActor($payload, creating: false);

            $dirtyAttributes = [];

            if (!empty($payload)) {
                $member->fill($payload);
                $dirtyAttributes = array_keys($member->getDirty());
                $member->save();
            }

            if (is_array($contacts)) {
                $this->syncContacts($member, $contacts);
            }

            if (is_array($families)) {
                $this->syncFamilies($member, $families);
            }

            if (is_array($customValues)) {
                $this->syncCustomValues($member, $customValues);
            }

            $updatedMember = $member->load(['contacts', 'families']);

            $this->auditLogService->record([
                'tenant_id' => $updatedMember->tenant_id,
                'user_id' => optional(Auth::user())->id,
                'action' => 'member.updated',
                'auditable_type' => Member::class,
                'auditable_id' => $updatedMember->id,
                'payload' => [
                    'updated_fields' => $dirtyAttributes,
                    'contacts_synced' => is_array($contacts),
                    'families_synced' => is_array($families),
                    'custom_values_synced' => is_array($customValues),
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);

            return $updatedMember;
        });
    }

    public function bulkImport(array $members): Collection
    {
        return DB::transaction(function () use ($members) {
            $payloads = collect($members);
            $first = $payloads->first();
            $tenantId = is_array($first) ? ($first['tenant_id'] ?? null) : null;
            $tenant = $this->resolveTenantContext($tenantId);

            if ($tenant) {
                $this->planEnforcementService->ensureCanUse($tenant, 'members', $payloads->count());
            }

            $createdMembers = collect();

            $payloads->chunk(25)->each(function (Collection $chunk) use (&$createdMembers) {
                $chunk->each(function (array $memberData) use (&$createdMembers) {
                    $createdMembers->push($this->create($memberData));
                });
            });

            return $createdMembers;
        });
    }

    public function delete(Member $member): void
    {
        DB::transaction(function () use ($member) {
            $this->performDelete($member);
        });
    }

    public function bulkDelete(array $memberUuids): int
    {
        return DB::transaction(function () use ($memberUuids) {
            $memberIds = collect($memberUuids);

            /** @var Collection<int, Member> $members */
            $members = Member::query()
                ->whereIn('uuid', $memberIds)
                ->get();

            $deleted = 0;

            $members->chunk(50)->each(function (Collection $chunk) use (&$deleted) {
                $chunk->each(function (Member $member) use (&$deleted) {
                    if ($this->performDelete($member)) {
                        $deleted++;
                    }
                });
            });

            return $deleted;
        });
    }

    public function restore(Member $member): Member
    {
        return DB::transaction(function () use ($member) {
            if (! $member->trashed()) {
                return $member->load(['contacts', 'families']);
            }

            $tenant = Tenant::query()->find($member->tenant_id);

            if ($tenant) {
                $this->planEnforcementService->ensureCanUse($tenant, 'members');
            }

            $member->restore();

            if ($user = Auth::user()) {
                $member->forceFill(['updated_by' => $user->id])->save();
            }

            $restoredMember = $member->load(['contacts', 'families']);

            if ($tenant) {
                $this->planEnforcementService->recordUsage($tenant, 'members');
            }

            $this->auditLogService->record([
                'tenant_id' => $restoredMember->tenant_id,
                'user_id' => optional(Auth::user())->id,
                'action' => 'member.restored',
                'auditable_type' => Member::class,
                'auditable_id' => $restoredMember->id,
                'payload' => [
                    'restored' => true,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);

            return $restoredMember;
        });
    }

    protected function performDelete(Member $member): bool
    {
        if ($member->trashed()) {
            return false;
        }

        $tenant = Tenant::query()->find($member->tenant_id);
        $user = Auth::user();

        if ($user) {
            $member->forceFill(['updated_by' => $user->id])->save();
        }

        $member->delete();

        if ($tenant) {
            $this->planEnforcementService->releaseUsage($tenant, 'members');
        }

        $this->auditLogService->record([
            'tenant_id' => $member->tenant_id,
            'user_id' => optional($user)->id,
            'action' => 'member.deleted',
            'auditable_type' => Member::class,
            'auditable_id' => $member->id,
            'payload' => [
                'deleted' => true,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);

        return true;
    }

    private function applyActor(array &$payload, bool $creating): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        if ($creating && empty($payload['created_by'])) {
            $payload['created_by'] = $user->id;
        }

        $payload['updated_by'] = $user->id;
    }

    private function syncContacts(Member $member, array $contacts): void
    {
        if (empty($contacts)) {
            $member->contacts()->delete();
            return;
        }

        $idsToKeep = [];

        foreach ($contacts as $contactData) {
            $contactId = $contactData['id'] ?? null;
            $payload = Arr::only($contactData, [
                'type',
                'label',
                'value',
                'is_primary',
                'is_emergency',
                'communication_preference',
            ]);
            $payload['tenant_id'] = $member->tenant_id;

            if ($contactId) {
                /** @var MemberContact|null $contact */
                $contact = $member->contacts()->whereKey($contactId)->first();
                if ($contact) {
                    $contact->fill($payload);
                    $contact->save();
                    $idsToKeep[] = $contact->id;
                    continue;
                }
            }

            $contact = $member->contacts()->create($payload);
            $idsToKeep[] = $contact->id;
        }

        if (!empty($idsToKeep)) {
            $member->contacts()
                ->whereNotIn('id', $idsToKeep)
                ->delete();
        }
    }

    private function syncFamilies(Member $member, array $families): void
    {
        $syncPayload = [];
        $tenantId = optional($this->tenantManager->getTenant())->id;

        foreach ($families as $familyData) {
            $familyId = $familyData['family_id'] ?? null;
            if (!$familyId) {
                continue;
            }

            $family = Family::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->find($familyId);

            if (!$family) {
                continue;
            }

            $syncPayload[$familyId] = [
                'relationship' => $familyData['relationship'] ?? 'other',
                'is_primary_contact' => (bool) ($familyData['is_primary_contact'] ?? false),
                'is_emergency_contact' => (bool) ($familyData['is_emergency_contact'] ?? false),
                'tenant_id' => $family->tenant_id,
            ];
        }

        $member->families()->sync($syncPayload);
    }

    private function syncCustomValues(Member $member, array $values): void
    {
        if (empty($values)) {
            $member->customValues()->delete();
            return;
        }

        $tenantId = optional($this->tenantManager->getTenant())->id;
        $fieldIds = array_filter(array_column($values, 'field_id'));

        /** @var \Illuminate\Database\Eloquent\Collection<int, MemberCustomField> $fields */
        $fields = MemberCustomField::query()
            ->whereIn('id', $fieldIds)
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->get()
            ->keyBy('id');

        /** @var \Illuminate\Database\Eloquent\Collection<int, MemberCustomValue> $existingValues */
        $existingValues = $member->customValues()
            ->whereIn('field_id', $fieldIds)
            ->get()
            ->keyBy('field_id');

        $idsToKeep = [];

        foreach ($values as $value) {
            $fieldId = $value['field_id'] ?? null;
            if (!$fieldId || !$fields->has($fieldId)) {
                continue;
            }

            $field = $fields->get($fieldId);
            $existingValue = $existingValues->get($fieldId);

            if (in_array($field->data_type, ['file', 'signature'], true)) {
                $metadata = $value['value'] ?? null;

                if (empty($metadata)) {
                    if ($existingValue) {
                        $this->customFieldFileService->delete($existingValue);
                        $existingValue->delete();
                        $existingValues->forget($fieldId);
                    }

                    continue;
                }

                if (!is_array($metadata)) {
                    continue;
                }

                if ($existingValue && $existingValue->value_json === $metadata) {
                    $idsToKeep[] = $existingValue->id;
                    continue;
                }

                if ($existingValue && $existingValue->hasStoredFile()) {
                    $this->customFieldFileService->delete($existingValue);
                }
            }

            $payload = $this->mapCustomFieldValue($field->data_type, $value['value'] ?? null);
            $payload['tenant_id'] = $member->tenant_id;

            $customValue = $member->customValues()->updateOrCreate(
                ['field_id' => $fieldId, 'tenant_id' => $member->tenant_id],
                $payload
            );

            $idsToKeep[] = $customValue->id;
            $existingValues->put($fieldId, $customValue);
        }

        if (!empty($idsToKeep)) {
            $member->customValues()->whereNotIn('id', $idsToKeep)->delete();
        }
    }

    private function mapCustomFieldValue(string $type, mixed $value): array
    {
        return match ($type) {
            'text' => [
                'value_string' => is_array($value) ? json_encode($value) : $value,
                'value_text' => is_string($value) ? $value : null,
                'value_number' => null,
                'value_date' => null,
                'value_boolean' => null,
                'value_json' => is_array($value) ? $value : null,
            ],
            'number' => [
                'value_string' => $value !== null ? (string) $value : null,
                'value_text' => null,
                'value_number' => $value !== null ? (float) $value : null,
                'value_date' => null,
                'value_boolean' => null,
                'value_json' => null,
            ],
            'date' => [
                'value_string' => $value,
                'value_text' => null,
                'value_number' => null,
                'value_date' => $value,
                'value_boolean' => null,
                'value_json' => null,
            ],
            'boolean' => [
                'value_string' => $value ? '1' : '0',
                'value_text' => null,
                'value_number' => null,
                'value_date' => null,
                'value_boolean' => (bool) $value,
                'value_json' => null,
            ],
            'select' => [
                'value_string' => is_array($value) ? implode(',', $value) : $value,
                'value_text' => null,
                'value_number' => null,
                'value_date' => null,
                'value_boolean' => null,
                'value_json' => null,
            ],
            'multi_select' => [
                'value_string' => null,
                'value_text' => null,
                'value_number' => null,
                'value_date' => null,
                'value_boolean' => null,
                'value_json' => is_array($value) ? array_values($value) : [$value],
            ],
            'file', 'signature' => is_array($value)
                ? $this->customFieldFileService->metadataFromArray($value)
                : [
                    'value_string' => null,
                    'value_text' => null,
                    'value_number' => null,
                    'value_date' => null,
                    'value_boolean' => null,
                    'value_json' => null,
                    'value_file_disk' => null,
                    'value_file_path' => null,
                    'value_file_name' => null,
                    'value_file_mime' => null,
                    'value_file_size' => null,
                ],
            default => throw new InvalidArgumentException("Unsupported custom field type: {$type}"),
        };
    }

    protected function maybeAutoStartMembershipProcess(Member $member): void
    {
        if (! $this->membershipProcessService) {
            return;
        }

        $process = MembershipProcess::query()
            ->forTenant($member->tenant_id)
            ->where('is_active', true)
            ->where('auto_start_on_member_create', true)
            ->orderBy('created_at')
            ->first();

        if (! $process) {
            return;
        }

        $existingRun = MemberProcessRun::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->where('process_id', $process->id)
            ->exists();

        if ($existingRun) {
            return;
        }

        $this->membershipProcessService->startProcess($member, $process);
    }

    protected function resolveTenantContext(?int $tenantId): ?Tenant
    {
        if ($this->tenantManager->hasTenant()) {
            return $this->tenantManager->getTenant();
        }

        if ($tenantId) {
            return Tenant::query()->find($tenantId);
        }

        return null;
    }
}
