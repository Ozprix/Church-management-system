<?php

namespace App\Services;

use App\Jobs\ProcessVisitorFollowupJob;
use App\Models\Member;
use App\Models\NotificationTemplate;
use App\Models\VisitorFollowup;
use App\Models\VisitorFollowupLog;
use App\Models\VisitorWorkflow;
use App\Models\VisitorWorkflowStep;
use App\Support\Tenancy\TenantManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VisitorAutomationService
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function startFollowup(Member $member, VisitorWorkflow $workflow): VisitorFollowup
    {
        $tenantId = $member->tenant_id;
        $firstStep = $this->activeSteps($workflow)->sortBy('step_number')->first();
        $now = Carbon::now();

        return DB::transaction(function () use ($member, $workflow, $firstStep, $tenantId, $now) {
            $status = 'completed';
            $nextRunAt = null;

            if ($firstStep) {
                $status = $firstStep->delay_minutes === 0 ? 'in_progress' : 'pending';
                $nextRunAt = $now->copy()->addMinutes($firstStep->delay_minutes);
            }

            $followup = VisitorFollowup::query()
                ->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'member_id' => $member->id,
                        'workflow_id' => $workflow->id,
                    ],
                    [
                        'status' => $status,
                        'started_at' => $now,
                        'next_run_at' => $nextRunAt,
                        'current_step_id' => null,
                    ]
                );

            if ($firstStep && $firstStep->delay_minutes === 0) {
                ProcessVisitorFollowupJob::dispatch($followup->id);
            }

            return $followup->fresh();
        });
    }

    public function haltFollowup(VisitorFollowup $followup): VisitorFollowup
    {
        $followup->status = 'halted';
        $followup->next_run_at = null;
        $followup->save();

        return $followup->fresh();
    }

    public function processDueFollowups(): int
    {
        $tenantId = optional($this->tenantManager->getTenant())->id;
        $query = VisitorFollowup::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', Carbon::now());

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $followups = $query->limit(100)->get();

        foreach ($followups as $followup) {
            ProcessVisitorFollowupJob::dispatch($followup->id);
        }

        return $followups->count();
    }

    public function runFollowupStep(VisitorFollowup $followup): VisitorFollowup
    {
        $workflow = $followup->workflow()->with('steps')->firstOrFail();
        $steps = $this->activeSteps($workflow)->sortBy('step_number')->values();

        $nextStep = $this->determineNextStep($followup, $steps);

        if (!$nextStep) {
            $followup->status = 'completed';
            $followup->completed_at = Carbon::now();
            $followup->next_run_at = null;
            $followup->current_step_id = null;
            $followup->save();

            VisitorFollowupLog::create([
                'followup_id' => $followup->id,
                'status' => 'skipped',
                'notes' => 'Workflow completed.',
                'run_at' => Carbon::now(),
            ]);

            return $followup->fresh();
        }

        $followup->status = 'in_progress';
        $followup->current_step_id = $nextStep->id;
        $followup->last_step_run_at = Carbon::now();

        $upcomingStep = $steps->first(fn ($step) => $step->step_number > $nextStep->step_number);
        $followup->next_run_at = $upcomingStep ? Carbon::now()->addMinutes($upcomingStep->delay_minutes) : null;
        $followup->save();

        $log = VisitorFollowupLog::create([
            'followup_id' => $followup->id,
            'step_id' => $nextStep->id,
            'status' => 'queued',
            'channel' => $nextStep->channel,
            'run_at' => Carbon::now(),
        ]);

        $this->dispatchStepNotification($followup, $nextStep, $log);

        return $followup->fresh();
    }

    protected function activeSteps(VisitorWorkflow $workflow): Collection
    {
        return $workflow->steps->where('is_active', true);
    }

    protected function determineNextStep(VisitorFollowup $followup, Collection $steps): ?VisitorWorkflowStep
    {
        if ($followup->current_step_id === null) {
            return $steps->sortBy('step_number')->first();
        }

        $currentStep = $steps->firstWhere('id', $followup->current_step_id);
        if (!$currentStep) {
            return $steps->sortBy('step_number')->first();
        }

        return $steps
            ->where('step_number', '>', $currentStep->step_number)
            ->sortBy('step_number')
            ->first();
    }

    protected function dispatchStepNotification(VisitorFollowup $followup, VisitorWorkflowStep $step, VisitorFollowupLog $log): void
    {
        if ($step->channel === 'staff_email') {
            $recipient = Arr::get($step->metadata, 'email') ?? $this->resolveStaffRecipient($followup->tenant_id);

            if (! $recipient) {
                $log->status = 'skipped';
                $log->notes = 'No staff recipient configured.';
                $log->save();

                return;
            }

            $notification = $this->notificationService->queue([
                'tenant_id' => $followup->tenant_id,
                'channel' => 'email',
                'recipient' => $recipient,
                'subject' => Arr::get($step->metadata, 'subject') ?? 'Visitor follow-up reminder',
                'body' => Arr::get($step->metadata, 'body') ?? sprintf(
                    "Please follow up with %s for step \"%s\".",
                    optional($followup->member)->first_name,
                    $step->name
                ),
            ]);

            $log->status = 'sent';
            $log->metadata = array_merge($log->metadata ?? [], [
                'notification_id' => $notification->id,
            ]);
            $log->save();

            return;
        }

        if (in_array($step->channel, ['email', 'sms'], true)) {
            $template = $step->notification_template_id
                ? NotificationTemplate::query()
                    ->where('tenant_id', $followup->tenant_id)
                    ->find($step->notification_template_id)
                : null;

            $payload = [
                'tenant_id' => $followup->tenant_id,
                'member_id' => $followup->member_id,
                'channel' => $step->channel,
                'notification_template_id' => $template?->id,
                'payload' => array_merge($step->metadata ?? [], [
                    'workflow' => $followup->workflow->name ?? null,
                    'step' => $step->name,
                ]),
                'body' => Arr::get($step->metadata, 'body'),
                'subject' => Arr::get($step->metadata, 'subject'),
            ];

            $notification = $this->notificationService->queue($payload);

            $log->status = 'sent';
            $log->metadata = array_merge($log->metadata ?? [], [
                'notification_id' => $notification->id,
            ]);
            $log->save();

            return;
        }

        $log->status = 'skipped';
        $log->notes = 'No delivery action required for this step.';
        $log->save();
    }

    protected function resolveStaffRecipient(int $tenantId): ?string
    {
        return \App\Models\User::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['visitor_manager', 'admin']))
            ->value('email');
    }
}
