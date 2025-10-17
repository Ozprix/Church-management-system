<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Tenant;
use App\Models\VolunteerAssignment;
use App\Models\VolunteerHour;
use App\Models\VolunteerRole;
use App\Models\VolunteerSignup;
use App\Models\VolunteerTeam;
use App\Services\VolunteerService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VolunteerPipelineService
{
    public function __construct(
        private readonly PlanEnforcementService $planEnforcementService,
        private readonly VolunteerService $volunteerService
    )
    {
    }

    public function submitSignup(array $attributes): VolunteerSignup
    {
        return DB::transaction(function () use ($attributes): VolunteerSignup {
            $tenantId = Arr::get($attributes, 'tenant_id');
            $tenant = Tenant::query()->findOrFail($tenantId);

            $this->planEnforcementService->ensureCanUse($tenant, 'volunteer_signups');

            $payload = Arr::only($attributes, [
                'tenant_id',
                'volunteer_role_id',
                'volunteer_team_id',
                'member_id',
                'name',
                'email',
                'phone',
                'notes',
                'metadata',
            ]);

            $payload['status'] = 'pending';
            $payload['applied_at'] = Arr::get($attributes, 'applied_at', Carbon::now());

            if (! empty($payload['volunteer_role_id'])) {
                VolunteerRole::query()->where('tenant_id', $tenantId)->findOrFail($payload['volunteer_role_id']);
            }

            if (! empty($payload['volunteer_team_id'])) {
                VolunteerTeam::query()->where('tenant_id', $tenantId)->findOrFail($payload['volunteer_team_id']);
            }

            if (! empty($payload['member_id'])) {
                Member::query()->where('tenant_id', $tenantId)->findOrFail($payload['member_id']);
            }

            $signup = VolunteerSignup::create($payload)->fresh(['role']);

            $this->planEnforcementService->recordUsage($tenant, 'volunteer_signups');
            $this->volunteerService->refreshRoleAnalytics($signup->role);

            return $signup;
        });
    }

    public function updateSignup(VolunteerSignup $signup, array $attributes): VolunteerSignup
    {
        return DB::transaction(function () use ($signup, $attributes): VolunteerSignup {
            $tenantId = $signup->tenant_id;
            $tenant = Tenant::query()->findOrFail($tenantId);
            $originalStatus = $signup->status;
            $originalRoleId = $signup->volunteer_role_id;

            if ($roleId = Arr::get($attributes, 'volunteer_role_id')) {
                VolunteerRole::query()->where('tenant_id', $tenantId)->findOrFail($roleId);
            }

            if ($teamId = Arr::get($attributes, 'volunteer_team_id')) {
                VolunteerTeam::query()->where('tenant_id', $tenantId)->findOrFail($teamId);
            }

            if ($memberId = Arr::get($attributes, 'member_id')) {
                Member::query()->where('tenant_id', $tenantId)->findOrFail($memberId);
            }

            $signup->fill(Arr::only($attributes, [
                'volunteer_role_id',
                'volunteer_team_id',
                'member_id',
                'name',
                'email',
                'phone',
                'status',
                'notes',
                'metadata',
            ]));

            if (array_key_exists('status', $attributes)) {
                $status = $attributes['status'];

                if ($status === 'reviewed') {
                    $signup->reviewed_at = $signup->reviewed_at ?? Carbon::now();
                }

                if (in_array($status, ['confirmed', 'assigned'], true)) {
                    $signup->confirmed_at = Carbon::now();
                    $signup->confirmed_by = auth()->id();
                }
            }

            $signup->save();

            if ($originalStatus === 'pending' && $signup->status !== 'pending') {
                $this->planEnforcementService->releaseUsage($tenant, 'volunteer_signups');
            }

            if ($originalStatus !== 'pending' && $signup->status === 'pending') {
                $this->planEnforcementService->ensureCanUse($tenant, 'volunteer_signups');
                $this->planEnforcementService->recordUsage($tenant, 'volunteer_signups');
            }

            $assignmentPayload = Arr::get($attributes, 'assignment');
            if ($signup->status === 'confirmed' && is_array($assignmentPayload)) {
                $assignmentData = Arr::only($assignmentPayload, ['starts_at', 'ends_at', 'gathering_id', 'volunteer_team_id', 'member_id']);
                $assignmentData['tenant_id'] = $signup->tenant_id;
                $assignmentData['volunteer_role_id'] = $signup->volunteer_role_id;
                $assignmentData['member_id'] = $assignmentData['member_id'] ?? $signup->member_id;
                $assignmentData['status'] = Arr::get($assignmentPayload, 'status', 'scheduled');

                if ($assignmentData['member_id']) {
                    $this->volunteerService->assign($assignmentData);
                    $signup->status = 'assigned';
                    $signup->save();
                    $this->planEnforcementService->releaseUsage($tenant, 'volunteer_signups');
                }
            }

            $signup->refresh();

            if ($originalRoleId && $originalRoleId !== $signup->volunteer_role_id) {
                $this->volunteerService->refreshRoleAnalytics(VolunteerRole::find($originalRoleId));
            }

            $this->volunteerService->refreshRoleAnalytics($signup->role);

            return $signup->fresh(['member', 'role', 'team']);
        });
    }

    public function recordHours(array $attributes): VolunteerHour
    {
        return DB::transaction(function () use ($attributes): VolunteerHour {
            $tenantId = Arr::get($attributes, 'tenant_id');

            if ($assignmentId = Arr::get($attributes, 'volunteer_assignment_id')) {
                VolunteerAssignment::query()->where('tenant_id', $tenantId)->findOrFail($assignmentId);
            }

            if ($memberId = Arr::get($attributes, 'member_id')) {
                Member::query()->where('tenant_id', $tenantId)->findOrFail($memberId);
            }

            return VolunteerHour::create(Arr::only($attributes, [
                'tenant_id',
                'volunteer_assignment_id',
                'member_id',
                'served_on',
                'hours',
                'source',
                'notes',
                'metadata',
            ]));
        });
    }

    public function confirmAssignment(VolunteerAssignment $assignment, ?int $userId = null): VolunteerAssignment
    {
        $assignment->forceFill([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'confirmed_by' => $userId ?? auth()->id(),
        ])->save();

        return $assignment->fresh(['member', 'role', 'team']);
    }

    public function deleteSignup(VolunteerSignup $signup): void
    {
        DB::transaction(function () use ($signup): void {
            $tenant = Tenant::query()->find($signup->tenant_id);
            $signup->load('role');
            $role = $signup->role;

            if ($tenant && $signup->status === 'pending') {
                $this->planEnforcementService->releaseUsage($tenant, 'volunteer_signups');
            }

            $signup->delete();

            $this->volunteerService->refreshRoleAnalytics($role);
        });
    }
}
