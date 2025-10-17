<?php

namespace App\Services;

use App\Models\Family;
use App\Models\Member;
use App\Support\Tenancy\TenantManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FamilyService
{
    public function __construct(private readonly TenantManager $tenantManager)
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
        $syncPayload = [];

        if (empty($members)) {
            $family->members()->detach();
            return;
        }

        foreach ($members as $memberData) {
            $memberId = $memberData['member_id'] ?? null;
            if (!$memberId) {
                continue;
            }

            $member = Member::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->find($memberId);

            if (!$member) {
                continue;
            }

            $syncPayload[$memberId] = [
                'relationship' => $memberData['relationship'] ?? 'other',
                'is_primary_contact' => (bool) ($memberData['is_primary_contact'] ?? false),
                'is_emergency_contact' => (bool) ($memberData['is_emergency_contact'] ?? false),
                'tenant_id' => $family->tenant_id,
            ];
        }

        $family->members()->sync($syncPayload);
    }
}
