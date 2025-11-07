<?php

namespace App\Services;

use App\Models\Family;
use App\Models\Member;
use App\Support\Tenancy\TenantManager;
use App\Services\NotificationService;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FamilyService
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly NotificationService $notificationService
    )
    {
    }

    public function create(array $attributes): Family
    {
        return DB::transaction(function () use ($attributes) {
            $members = $attributes['members'] ?? [];
            $payload = Arr::except($attributes, ['members']);
            $this->applyActor($payload, creating: true);

            $family = Family::create($payload);
            $this->syncMembers($family, $members);

            return $family->load('members');
        });
    }

    public function update(Family $family, array $attributes): Family
    {
        return DB::transaction(function () use ($family, $attributes) {
            $members = $attributes['members'] ?? null;
            $payload = Arr::except($attributes, ['members']);
            $this->applyActor($payload, creating: false);

            if (!empty($payload)) {
                $family->fill($payload);
                $family->save();
            }

            if (is_array($members)) {
                $this->syncMembers($family, $members);
            }

            return $family->load('members');
        });
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

    private function syncMembers(Family $family, array $members): void
    {
        $tenantId = optional($this->tenantManager->getTenant())->id;
        $previousAssignments = $family->members()
            ->withPivot(['relationship', 'is_primary_contact', 'is_emergency_contact'])
            ->get()
            ->mapWithKeys(fn (Member $member) => [
                $member->id => [
                    'relationship' => $member->pivot->relationship,
                    'is_primary_contact' => (bool) $member->pivot->is_primary_contact,
                    'is_emergency_contact' => (bool) $member->pivot->is_emergency_contact,
                ],
            ]);

        $explicitPrimaryFalse = [];
        $explicitEmergencyFalse = [];

        if (empty($members)) {
            $family->members()->detach();
            $this->orchestrateHousehold($family, $previousAssignments, $explicitPrimaryFalse, $explicitEmergencyFalse);

            return;
        }

        $syncPayload = [];

        foreach ($members as $memberData) {
            $memberId = $memberData['member_id'] ?? null;
            if (! $memberId) {
                continue;
            }

            $member = Member::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->find($memberId);

            if (! $member) {
                continue;
            }

            $attributes = [
                'relationship' => $memberData['relationship'] ?? 'other',
                'tenant_id' => $family->tenant_id,
            ];

            if (array_key_exists('is_primary_contact', $memberData)) {
                $isPrimary = (bool) $memberData['is_primary_contact'];
                $attributes['is_primary_contact'] = $isPrimary;
                if (! $isPrimary) {
                    $explicitPrimaryFalse[] = $memberId;
                }
            }

            if (array_key_exists('is_emergency_contact', $memberData)) {
                $isEmergency = (bool) $memberData['is_emergency_contact'];
                $attributes['is_emergency_contact'] = $isEmergency;
                if (! $isEmergency) {
                    $explicitEmergencyFalse[] = $memberId;
                }
            }

            $syncPayload[$memberId] = $attributes;
        }

        $family->members()->sync($syncPayload);
        $family->load('members.contacts');

        $this->orchestrateHousehold($family, $previousAssignments, $explicitPrimaryFalse, $explicitEmergencyFalse);
    }

    /**
     * @param Collection<int, array<string, mixed>> $previousAssignments
     */
    private function orchestrateHousehold(
        Family $family,
        Collection $previousAssignments,
        array $explicitPrimaryFalse = [],
        array $explicitEmergencyFalse = []
    ): void
    {
        $family->loadMissing(['members.contacts']);
        $members = $family->members;

        if ($members->isEmpty()) {
            return;
        }

        $currentAssignments = $members->mapWithKeys(function (Member $member) {
            return [
                $member->id => [
                    'relationship' => $member->pivot->relationship,
                    'is_primary_contact' => (bool) $member->pivot->is_primary_contact,
                    'is_emergency_contact' => (bool) $member->pivot->is_emergency_contact,
                ],
            ];
        });

        $desired = $currentAssignments->map(fn (array $data) => $data)->all();

        $primaryIds = collect($desired)
            ->filter(fn (array $data) => $data['is_primary_contact'])
            ->keys();

        if ($primaryIds->isEmpty()) {
            $candidate = $members
                ->first(function (Member $member) use ($desired, $explicitPrimaryFalse) {
                    if (in_array($member->id, $explicitPrimaryFalse, true)) {
                        return false;
                    }

                    return in_array(
                        strtolower((string) ($desired[$member->id]['relationship'] ?? '')),
                        ['head', 'guardian'],
                        true
                    );
                });

            if (! $candidate) {
                $candidate = $members->first(fn (Member $member) => ! in_array($member->id, $explicitPrimaryFalse, true));
                if ($candidate) {
                    $existing = $desired[$candidate->id] ?? [];
                    $existing['relationship'] = 'head';
                    $desired[$candidate->id] = $existing;
                }
            }

            if ($candidate) {
                foreach ($desired as $memberId => &$assignment) {
                    if (in_array($memberId, $explicitPrimaryFalse, true)) {
                        $assignment['is_primary_contact'] = false;
                        continue;
                    }

                    $assignment['is_primary_contact'] = $memberId === $candidate->id;
                }
                unset($assignment);
            }
    } else {
        $previousPrimaryId = $previousAssignments
            ->filter(fn (array $data) => $data['is_primary_contact'] ?? false)
            ->keys()
            ->first();

        $primaryCandidates = $primaryIds->all();
        $primaryId = $previousPrimaryId && in_array($previousPrimaryId, $primaryCandidates, true)
            ? $previousPrimaryId
            : $primaryIds->first();
            if ($primaryId !== null) {
                foreach ($desired as $memberId => &$assignment) {
                    $assignment['is_primary_contact'] = $memberId === $primaryId;
                }
                unset($assignment);

                if (! in_array(strtolower((string) ($desired[$primaryId]['relationship'] ?? '')), ['head', 'guardian'], true)) {
                    $existing = $desired[$primaryId];
                    $existing['relationship'] = 'head';
                    $desired[$primaryId] = $existing;
                }
            }
        }

        $desiredPrimaryId = $desired
            ? collect($desired)
                ->filter(fn (array $data) => $data['is_primary_contact'])
                ->keys()
                ->first()
            : null;

        $hasEmergency = collect($desired)->contains(fn (array $data) => $data['is_emergency_contact']);
        if (! $hasEmergency) {
            $candidateEmergency = $members
                ->first(function (Member $member) use ($desired, $explicitEmergencyFalse, $desiredPrimaryId) {
                    if (in_array($member->id, $explicitEmergencyFalse, true)) {
                        return false;
                    }

                    return $desiredPrimaryId !== null && $member->id === $desiredPrimaryId;
                });

            if (! $candidateEmergency) {
                $candidateEmergency = $members->first(function (Member $member) use ($explicitEmergencyFalse) {
                    return ! in_array($member->id, $explicitEmergencyFalse, true);
                });
            }

            if ($candidateEmergency) {
                $assignment = $desired[$candidateEmergency->id];
                $assignment['is_emergency_contact'] = true;
                $desired[$candidateEmergency->id] = $assignment;
                $desiredPrimaryId ??= $candidateEmergency->id;
            }
        }

        foreach ($desired as $memberId => $assignment) {
            $current = $currentAssignments->get($memberId);
            if ($current === null) {
                continue;
            }

            if ($current != $assignment) {
                $family->members()->updateExistingPivot($memberId, $assignment);
            }
        }

        $family->load('members.contacts');

        $currentPrimaryId = collect($desired)
            ->filter(fn (array $data) => $data['is_primary_contact'])
            ->keys()
            ->first();
        $previousPrimaryId = $previousAssignments
            ->filter(fn (array $data) => $data['is_primary_contact'] ?? false)
            ->keys()
            ->first();

        if ($currentPrimaryId && $currentPrimaryId !== $previousPrimaryId) {
            $primaryMember = $family->members->firstWhere('id', $currentPrimaryId);
            if ($primaryMember) {
                $this->notifyMemberRoleChange($primaryMember, $family, 'primary');
            }
        }

        $currentEmergencyId = collect($desired)
            ->filter(fn (array $data) => $data['is_emergency_contact'] ?? false)
            ->keys()
            ->first();

        $previousEmergencyId = $previousAssignments
            ->filter(fn (array $data) => $data['is_emergency_contact'] ?? false)
            ->keys()
            ->first();

        if (
            $currentEmergencyId
            && $currentEmergencyId !== $previousEmergencyId
            && $currentEmergencyId !== $currentPrimaryId
        ) {
            $emergencyMember = $family->members->firstWhere('id', $currentEmergencyId);
            if ($emergencyMember) {
                $this->notifyMemberRoleChange($emergencyMember, $family, 'emergency');
            }
        }
    }

    private function notifyMemberRoleChange(Member $member, Family $family, string $role): void
    {
        $member->loadMissing('contacts');
        $contact = $member->preferredContact();

        if (! $contact || $contact->type !== 'email' || ! filter_var($contact->value, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $preferredName = $member->preferred_name ?: $member->first_name;

        $subject = match ($role) {
            'primary' => 'Primary household contact assignment',
            'emergency' => 'Emergency household contact assignment',
            default => 'Household contact update',
        };

        $body = match ($role) {
            'primary' => sprintf(
                "Hi %s,\n\nYou're now listed as the primary contact for the %s household. You'll receive future communications and reminders for this family.",
                $preferredName,
                $family->family_name
            ),
            'emergency' => sprintf(
                "Hi %s,\n\nYou're now listed as the emergency contact for the %s household. We'll reach out to you if urgent needs arise for this family.",
                $preferredName,
                $family->family_name
            ),
            default => sprintf(
                "Hi %s,\n\nYour household contact preferences for the %s family have been updated.",
                $preferredName,
                $family->family_name
            ),
        };

        $this->notificationService->queue([
            'tenant_id' => $family->tenant_id,
            'member_id' => $member->id,
            'channel' => 'email',
            'subject' => $subject,
            'body' => $body,
            'payload' => [
                'family_id' => $family->id,
                'family_name' => $family->family_name,
                'role' => $role,
            ],
        ]);
    }
}
