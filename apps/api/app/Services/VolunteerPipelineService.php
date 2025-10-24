<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VolunteerAssignment;
use App\Models\VolunteerHour;
use App\Models\VolunteerRole;
use App\Models\VolunteerSignup;
use App\Models\VolunteerTeam;
use App\Services\NotificationService;
use App\Services\VolunteerService;
use App\Support\VolunteerSignupStage;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VolunteerPipelineService
{
    public function __construct(
        private readonly PlanEnforcementService $planEnforcementService,
        private readonly VolunteerService $volunteerService,
        private readonly NotificationService $notificationService
    ) {
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
            $payload['stage'] = VolunteerSignupStage::APPLIED;

            if (! empty($payload['volunteer_role_id'])) {
                VolunteerRole::query()->where('tenant_id', $tenantId)->findOrFail($payload['volunteer_role_id']);
            }

            if (! empty($payload['volunteer_team_id'])) {
                VolunteerTeam::query()->where('tenant_id', $tenantId)->findOrFail($payload['volunteer_team_id']);
            }

            if (! empty($payload['member_id'])) {
                Member::query()->where('tenant_id', $tenantId)->findOrFail($payload['member_id']);
            }

            $signup = VolunteerSignup::create($payload)->fresh(['member', 'role', 'team']);

            $this->recordStageTransition($signup, VolunteerSignupStage::APPLIED, [
                'notes' => Arr::get($attributes, 'notes'),
                'user_id' => auth()->id(),
                'force' => true,
            ], notify: false, tenant: $tenant);

            $this->planEnforcementService->recordUsage($tenant, 'volunteer_signups');
            $this->volunteerService->refreshRoleAnalytics($signup->role);

            $this->notifyCoordinators(
                $tenant,
                sprintf('New volunteer signup: %s', $signup->name ?? ($signup->member?->full_name ?? 'Unknown applicant')),
                $this->buildCoordinatorMessage($signup, VolunteerSignupStage::APPLIED, 'submitted')
            );

            if ($signup->email) {
                $this->notifyVolunteer(
                    $signup,
                    'Thanks for serving!',
                    'We received your volunteer application and will be in touch soon.'
                );
            }

            return $signup->fresh(['member', 'role', 'team']);
        });
    }

    public function updateSignup(VolunteerSignup $signup, array $attributes): VolunteerSignup
    {
        return DB::transaction(function () use ($signup, $attributes): VolunteerSignup {
            $tenantId = $signup->tenant_id;
            $tenant = Tenant::query()->findOrFail($tenantId);
            $originalStatus = $signup->status;
            $originalRoleId = $signup->volunteer_role_id;

            $stage = Arr::pull($attributes, 'stage');
            $stageNotes = Arr::pull($attributes, 'stage_notes');
            $followUpAt = Arr::pull($attributes, 'follow_up_at');
            $lastContactedAt = Arr::pull($attributes, 'last_contacted_at');
            $checklist = Arr::pull($attributes, 'onboarding_checklist');

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

            if ($stage) {
                $signup = $this->advanceStage($signup, $stage, [
                    'notes' => $stageNotes,
                    'follow_up_at' => $followUpAt,
                    'last_contacted_at' => $lastContactedAt,
                    'onboarding_checklist' => $checklist,
                    'user_id' => auth()->id(),
                ]);
            } elseif ($followUpAt || $lastContactedAt || $checklist) {
                $this->updateStageMeta($signup, [
                    'follow_up_at' => $followUpAt,
                    'last_contacted_at' => $lastContactedAt,
                    'onboarding_checklist' => $checklist,
                ]);
                $signup->refresh();
            }

            $this->volunteerService->refreshRoleAnalytics($signup->role);

            return $signup->fresh(['member', 'role', 'team']);
        });
    }

    public function advanceStage(VolunteerSignup $signup, string $stage, array $context = []): VolunteerSignup
    {
        return DB::transaction(function () use ($signup, $stage, $context): VolunteerSignup {
            $tenant = Tenant::query()->findOrFail($signup->tenant_id);

            return $this->recordStageTransition(
                $signup->fresh(['member', 'role', 'team']),
                $stage,
                array_merge($context, ['user_id' => $context['user_id'] ?? auth()->id()]),
                notify: true,
                tenant: $tenant
            );
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

    protected function updateStageMeta(VolunteerSignup $signup, array $meta): void
    {
        $updates = [];

        if (! empty($meta['follow_up_at'])) {
            $updates['follow_up_at'] = Carbon::parse($meta['follow_up_at']);
        }

        if (! empty($meta['last_contacted_at'])) {
            $updates['last_contacted_at'] = Carbon::parse($meta['last_contacted_at']);
        }

        if (array_key_exists('onboarding_checklist', $meta) && $meta['onboarding_checklist'] !== null) {
            $updates['onboarding_checklist'] = $meta['onboarding_checklist'];
        }

        if (! empty($updates)) {
            $signup->forceFill($updates)->save();
        }
    }

    protected function recordStageTransition(
        VolunteerSignup $signup,
        string $stage,
        array $context = [],
        bool $notify = true,
        ?Tenant $tenant = null
    ): VolunteerSignup {
        if (! in_array($stage, VolunteerSignupStage::values(), true)) {
            throw ValidationException::withMessages([
                'stage' => sprintf('Stage "%s" is not supported.', $stage),
            ]);
        }

        $tenant ??= Tenant::query()->findOrFail($signup->tenant_id);

        $previousStage = $signup->stage;
        $history = $signup->stage_history ?? [];

        if ($previousStage === $stage && empty($context['force'])) {
            $this->updateStageMeta($signup, $context);

            return $signup->fresh(['member', 'role', 'team']);
        }

        $history[] = [
            'from' => $previousStage === $stage ? null : $previousStage,
            'to' => $stage,
            'label' => VolunteerSignupStage::label($stage),
            'notes' => $context['notes'] ?? null,
            'changed_by' => $context['user_id'] ?? auth()->id(),
            'changed_at' => Carbon::now()->toIso8601String(),
        ];

        $updates = [
            'stage' => $stage,
            'stage_history' => $history,
        ];

        if (! empty($context['follow_up_at'])) {
            $updates['follow_up_at'] = Carbon::parse($context['follow_up_at']);
        }

        if (! empty($context['last_contacted_at'])) {
            $updates['last_contacted_at'] = Carbon::parse($context['last_contacted_at']);
        }

        if (array_key_exists('onboarding_checklist', $context) && $context['onboarding_checklist'] !== null) {
            $updates['onboarding_checklist'] = $context['onboarding_checklist'];
        }

        $statusUpdates = $this->determineStatusForStage($signup, $stage, $context['user_id'] ?? auth()->id());
        $updates = array_merge($updates, $statusUpdates);

        $signup->forceFill($updates)->save();

        if ($previousStage === VolunteerSignupStage::APPLIED && $stage !== VolunteerSignupStage::APPLIED) {
            $this->planEnforcementService->releaseUsage($tenant, 'volunteer_signups');
        } elseif ($previousStage !== VolunteerSignupStage::APPLIED && $stage === VolunteerSignupStage::APPLIED) {
            $this->planEnforcementService->ensureCanUse($tenant, 'volunteer_signups');
            $this->planEnforcementService->recordUsage($tenant, 'volunteer_signups');
        }

        if (! empty($context['assignment']) && $stage === VolunteerSignupStage::READY && is_array($context['assignment'])) {
            $assignmentData = Arr::only($context['assignment'], ['starts_at', 'ends_at', 'gathering_id', 'volunteer_team_id', 'member_id', 'status']);
            $assignmentData['tenant_id'] = $signup->tenant_id;
            $assignmentData['volunteer_role_id'] = $context['assignment']['volunteer_role_id'] ?? $signup->volunteer_role_id;
            $assignmentData['member_id'] = $assignmentData['member_id'] ?? $signup->member_id;

            if (! empty($assignmentData['member_id']) && ! empty($assignmentData['volunteer_role_id'])) {
                $this->volunteerService->assign($assignmentData);
                $signup->forceFill(['status' => 'assigned'])->save();
            }
        }

        $signup->load(['member', 'role', 'team']);
        $this->volunteerService->refreshRoleAnalytics($signup->role);

        if ($notify) {
            $this->notifyCoordinators(
                $tenant,
                sprintf('Volunteer signup moved to %s', VolunteerSignupStage::label($stage)),
                $this->buildCoordinatorMessage($signup, $stage, 'updated'),
                Arr::get($context, 'notes')
            );

            if ($stage === VolunteerSignupStage::READY && $signup->email) {
                $this->notifyVolunteer(
                    $signup,
                    'You are ready to serve!',
                    'Thanks for completing onboarding. A coordinator will reach out with your next assignment soon.'
                );
            }
        }

        return $signup->fresh(['member', 'role', 'team']);
    }

    protected function determineStatusForStage(VolunteerSignup $signup, string $stage, ?int $userId): array
    {
        $updates = [];

        if ($stage === VolunteerSignupStage::REVIEW && $signup->status === 'pending') {
            $updates['status'] = 'reviewed';
            $updates['reviewed_at'] = $signup->reviewed_at ?? Carbon::now();
        }

        if ($stage === VolunteerSignupStage::READY && ! in_array($signup->status, ['assigned', 'confirmed'], true)) {
            $updates['status'] = 'confirmed';
            $updates['confirmed_at'] = Carbon::now();
            $updates['confirmed_by'] = $userId;
        }

        if ($stage === VolunteerSignupStage::INACTIVE) {
            $updates['status'] = 'archived';
        }

        return $updates;
    }

    protected function notifyCoordinators(Tenant $tenant, string $subject, string $body, ?string $notes = null): void
    {
        $recipients = $tenant->users()
            ->with('roles.permissions', 'permissions')
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('volunteer_pipeline.manage_signups') && ! empty($user->email));

        if ($recipients->isEmpty()) {
            $fallback = $tenant->users()->whereNotNull('email')->first();
            if ($fallback) {
                $recipients = collect([$fallback]);
            }
        }

        foreach ($recipients as $user) {
            $message = $body;
            if ($notes) {
                $message .= "\n\nCoordinator notes: " . $notes;
            }

            $this->notificationService->queue([
                'tenant_id' => $tenant->id,
                'channel' => 'email',
                'recipient' => $user->email,
                'subject' => $subject,
                'body' => $message,
            ]);
        }
    }

    protected function notifyVolunteer(VolunteerSignup $signup, string $subject, string $body): void
    {
        if (! $signup->email) {
            return;
        }

        $this->notificationService->queue([
            'tenant_id' => $signup->tenant_id,
            'channel' => 'email',
            'recipient' => $signup->email,
            'subject' => $subject,
            'body' => $body,
        ]);
    }

    protected function buildCoordinatorMessage(VolunteerSignup $signup, string $stage, string $action = 'updated'): string
    {
        $applicant = $signup->member?->full_name ?? $signup->name ?? 'Unknown applicant';

        $lines = [
            sprintf('%s has been %s to the %s stage.', $applicant, $action, VolunteerSignupStage::label($stage)),
        ];

        if ($signup->role?->name) {
            $lines[] = 'Role: ' . $signup->role->name;
        }

        if ($signup->team?->name) {
            $lines[] = 'Team: ' . $signup->team->name;
        }

        if ($signup->email) {
            $lines[] = 'Email: ' . $signup->email;
        }

        if ($signup->phone) {
            $lines[] = 'Phone: ' . $signup->phone;
        }

        if ($signup->follow_up_at) {
            $lines[] = 'Next follow-up: ' . $signup->follow_up_at->format('F j, Y g:i A');
        }

        return implode("\n", $lines);
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
