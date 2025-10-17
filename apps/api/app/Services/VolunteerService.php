<?php

namespace App\Services;

use App\Models\Gathering;
use App\Models\Member;
use App\Models\VolunteerAssignment;
use App\Models\VolunteerAvailability;
use App\Models\VolunteerRole;
use App\Models\VolunteerTeam;
use App\Models\VolunteerSignup;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VolunteerService
{
    public function createRole(array $attributes): VolunteerRole
    {
        $role = VolunteerRole::create(Arr::only($attributes, [
            'tenant_id',
            'name',
            'slug',
            'description',
            'skills_required',
        ]));

        $this->refreshRoleAnalytics($role);

        return $role;
    }

    public function updateRole(VolunteerRole $role, array $attributes): VolunteerRole
    {
        $role->fill(Arr::only($attributes, ['name', 'slug', 'description', 'skills_required']));
        $role->save();

        if (array_key_exists('team_ids', $attributes)) {
            $teamIds = array_filter((array) $attributes['team_ids']);
            $role->teams()->sync($teamIds);
        }

        $role->refresh();
        $this->refreshRoleAnalytics($role);

        return $role->fresh('teams');
    }

    public function createTeam(array $attributes): VolunteerTeam
    {
        $team = VolunteerTeam::create(Arr::only($attributes, [
            'tenant_id',
            'name',
            'slug',
            'description',
            'metadata',
        ]));

        if (! empty($attributes['role_ids'])) {
            $team->roles()->sync($attributes['role_ids']);
        }

        return $team->fresh('roles');
    }

    public function updateTeam(VolunteerTeam $team, array $attributes): VolunteerTeam
    {
        $team->fill(Arr::only($attributes, ['name', 'slug', 'description', 'metadata']));
        $team->save();

        if (array_key_exists('role_ids', $attributes)) {
            $team->roles()->sync(array_filter((array) $attributes['role_ids']));
        }

        return $team->fresh('roles');
    }

    public function assign(array $attributes): VolunteerAssignment
    {
        $tenantId = $attributes['tenant_id'] ?? null;

        $assignmentAttributes = Arr::only($attributes, [
            'tenant_id',
            'member_id',
            'volunteer_role_id',
            'volunteer_team_id',
            'gathering_id',
            'starts_at',
            'ends_at',
            'status',
            'notes',
        ]);

        if (! empty($assignmentAttributes['member_id'])) {
            Member::query()->where('tenant_id', $tenantId)->findOrFail($assignmentAttributes['member_id']);
        }

        if (! empty($assignmentAttributes['volunteer_role_id'])) {
            VolunteerRole::query()->where('tenant_id', $tenantId)->findOrFail($assignmentAttributes['volunteer_role_id']);
        }

        if (! empty($assignmentAttributes['volunteer_team_id'])) {
            VolunteerTeam::query()->where('tenant_id', $tenantId)->findOrFail($assignmentAttributes['volunteer_team_id']);
        }

        if (! empty($assignmentAttributes['gathering_id'])) {
            Gathering::query()->where('tenant_id', $tenantId)->findOrFail($assignmentAttributes['gathering_id']);
        }

        $assignment = VolunteerAssignment::create($assignmentAttributes);
        $this->incrementRoleAssignmentCount($assignment->role);

        return $assignment->fresh(['member', 'role', 'team', 'gathering']);
    }

    public function updateAssignment(VolunteerAssignment $assignment, array $attributes): VolunteerAssignment
    {
        $assignment->fill(Arr::only($attributes, [
            'volunteer_role_id',
            'volunteer_team_id',
            'gathering_id',
            'starts_at',
            'ends_at',
            'status',
            'notes',
        ]));

        if ($assignment->isDirty('volunteer_role_id')) {
            $originalRoleId = $assignment->getOriginal('volunteer_role_id');
            VolunteerRole::query()->where('tenant_id', $assignment->tenant_id)->findOrFail($assignment->volunteer_role_id);
            $assignment->save();

            if ($originalRoleId) {
                $this->refreshRoleAnalytics(VolunteerRole::find($originalRoleId));
            }

            $this->refreshRoleAnalytics($assignment->role);
            return $assignment->fresh(['member', 'role', 'team', 'gathering']);
        }

        if ($assignment->isDirty('volunteer_team_id') && $assignment->volunteer_team_id) {
            VolunteerTeam::query()->where('tenant_id', $assignment->tenant_id)->findOrFail($assignment->volunteer_team_id);
        }

        if ($assignment->isDirty('gathering_id') && $assignment->gathering_id) {
            Gathering::query()->where('tenant_id', $assignment->tenant_id)->findOrFail($assignment->gathering_id);
        }

        $assignment->save();

        $this->refreshRoleAnalytics($assignment->role);

        return $assignment->fresh(['member', 'role', 'team', 'gathering']);
    }

    public function swapAssignment(VolunteerAssignment $fromAssignment, VolunteerAssignment $toAssignment): void
    {
        DB::transaction(function () use ($fromAssignment, $toAssignment): void {
            $fromMemberId = $fromAssignment->member_id;
            $toMemberId = $toAssignment->member_id;

            $fromAssignment->update([
                'member_id' => $toMemberId,
                'status' => 'swapped',
            ]);

            $toAssignment->update([
                'member_id' => $fromMemberId,
                'status' => 'swapped',
            ]);
        });

        $fromAssignment->load('role');
        $toAssignment->load('role');

        $this->refreshRoleAnalytics($fromAssignment->role);
        $this->refreshRoleAnalytics($toAssignment->role);
    }

    public function updateAvailability(array $attributes): VolunteerAvailability
    {
        $tenantId = $attributes['tenant_id'] ?? null;
        $memberId = $attributes['member_id'] ?? null;

        Member::query()->where('tenant_id', $tenantId)->findOrFail($memberId);

        return VolunteerAvailability::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'member_id' => $memberId],
            Arr::only($attributes, ['weekdays', 'time_blocks', 'unavailable_from', 'unavailable_until', 'notes'])
        );
    }

    public function upcomingAssignments(int $tenantId, ?int $memberId = null, ?Carbon $from = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = VolunteerAssignment::query()
            ->with(['member', 'role', 'team', 'gathering'])
            ->where('tenant_id', $tenantId)
            ->where('starts_at', '>=', $from ?? Carbon::now())
            ->orderBy('starts_at');

        if ($memberId) {
            $query->where('member_id', $memberId);
        }

        return $query->get();
    }

    public function refreshRoleAnalytics(?VolunteerRole $role): void
    {
        if (! $role) {
            return;
        }

        $role->forceFill([
            'active_assignment_count' => (int) $role->assignments()->whereNotIn('status', ['canceled', 'completed'])->count(),
            'pending_signup_count' => (int) VolunteerSignup::query()
                ->where('tenant_id', $role->tenant_id)
                ->where('volunteer_role_id', $role->id)
                ->where('status', 'pending')
                ->count(),
        ])->save();
    }

    protected function incrementRoleAssignmentCount(?VolunteerRole $role): void
    {
        if (! $role) {
            return;
        }

        $this->refreshRoleAnalytics($role);
    }
}
