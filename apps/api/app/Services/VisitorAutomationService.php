<?php

namespace App\Services;

use App\Jobs\ProcessVisitorFollowupJob;
use App\Models\AttendanceRecord;
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
        $followup->loadMissing('member');
        $workflow = $followup->workflow()->with('steps')->firstOrFail();
        $steps = $this->activeSteps($workflow);
        $context = $this->buildFollowupContext($followup);

        $nextStep = $this->determineNextStep($followup, $steps, $context);

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

    protected function determineNextStep(VisitorFollowup $followup, Collection $steps, array $context): ?VisitorWorkflowStep
    {
        $sortedSteps = $steps->sortBy('step_number')->values();
        $startIndex = 0;

        if ($followup->current_step_id !== null) {
            $currentIndex = $sortedSteps->search(fn ($step) => $step->id === $followup->current_step_id);
            $startIndex = $currentIndex === false ? 0 : $currentIndex + 1;
        }

        for ($index = $startIndex; $index < $sortedSteps->count(); $index++) {
            /** @var \App\Models\VisitorWorkflowStep $step */
            $step = $sortedSteps->get($index);
            [$shouldRun, $failureReason] = $this->evaluateStepRules($step, $context);

            if ($shouldRun) {
                return $step;
            }

            if ($this->stepHasRules($step)) {
                $this->logStepSkipped(
                    $followup,
                    $step,
                    $failureReason ?? 'Automation rules did not match this visitor.'
                );
            }
        }

        return null;
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

        if ($step->channel === 'task') {
            $log->status = 'pending';
            $log->notes = $log->notes ?: 'Manual follow-up required.';
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

    protected function buildFollowupContext(VisitorFollowup $followup): array
    {
        $member = $followup->member;
        if (! $member) {
            return [];
        }

        $hasFamily = $member->families()->exists();

        $visitCount = AttendanceRecord::query()
            ->where('tenant_id', $followup->tenant_id)
            ->where('member_id', $followup->member_id)
            ->where('status', 'present')
            ->count();

        $lastAttendance = AttendanceRecord::query()
            ->where('tenant_id', $followup->tenant_id)
            ->where('member_id', $followup->member_id)
            ->where('status', 'present')
            ->latest('checked_in_at')
            ->first();

        $daysSinceLastAttendance = $lastAttendance && $lastAttendance->checked_in_at
            ? $lastAttendance->checked_in_at->diffInDays(Carbon::now())
            : null;

        $tags = [];
        if (method_exists($member, 'tags')) {
            $member->loadMissing('tags');
            $tagsRelation = $member->getRelation('tags');
            if ($tagsRelation) {
                $tags = collect($tagsRelation)
                    ->pluck('slug')
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        return [
            'member_status' => $member->membership_status,
            'member_stage' => $member->membership_stage,
            'member_tags' => $tags,
            'visit_count' => $visitCount,
            'days_since_last_attended' => $daysSinceLastAttendance,
            'has_family_assignment' => $hasFamily,
        ];
    }

    protected function evaluateStepRules(VisitorWorkflowStep $step, array $context): array
    {
        $metadata = $step->metadata ?? [];
        $rules = is_array($metadata) ? Arr::get($metadata, 'rules') : null;

        if (! is_array($rules) || empty($rules)) {
            return [true, null];
        }

        if (empty($context)) {
            return [false, 'Unable to evaluate automation rules for this visitor.'];
        }

        $logic = Arr::get($metadata, 'rules_logic') === 'any' ? 'any' : 'all';
        $anyPassed = false;
        $failureReason = null;

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                $failureReason = 'Invalid rule configuration.';
                if ($logic === 'all') {
                    return [false, $failureReason];
                }
                continue;
            }

            $evaluation = $this->evaluateRule($rule, $context);
            if ($evaluation['result']) {
                $anyPassed = true;
                if ($logic === 'any') {
                    return [true, null];
                }
                continue;
            }

            $failureReason = $evaluation['reason'];
            if ($logic === 'all') {
                return [false, $failureReason];
            }
        }

        if ($logic === 'all') {
            return [true, null];
        }

        return [$anyPassed, $anyPassed ? null : ($failureReason ?? 'No automation rule matched this visitor.')];
    }

    protected function evaluateRule(array $rule, array $context): array
    {
        $field = isset($rule['field']) ? (string) $rule['field'] : null;
        $operator = isset($rule['operator']) ? (string) $rule['operator'] : null;
        $value = $rule['value'] ?? null;

        if (! $field || ! $operator) {
            return ['result' => false, 'reason' => 'Invalid rule configuration.'];
        }

        $fieldLabel = self::RULE_FIELD_LABELS[$field] ?? ucfirst(str_replace('_', ' ', $field));
        $operatorLabel = self::RULE_OPERATOR_LABELS[$operator] ?? $operator;

        switch ($field) {
            case 'member_status':
            case 'member_stage': {
                $actual = Arr::get($context, $field);
                return $this->evaluateStringRule($actual, $value, $operator, $fieldLabel, $operatorLabel);
            }
            case 'member_tags': {
                $tags = Arr::get($context, 'member_tags');
                $tags = is_array($tags) ? array_map('strtolower', $tags) : [];
                $expected = is_string($value) ? strtolower($value) : null;

                if ($expected === null) {
                    return ['result' => false, 'reason' => 'Invalid tag value in rule configuration.'];
                }

                if ($operator === 'contains') {
                    $result = in_array($expected, $tags, true);
                    return [
                        'result' => $result,
                        'reason' => $result
                            ? null
                            : sprintf(
                                'Condition failed: %s %s %s (current: %s)',
                                $fieldLabel,
                                $operatorLabel,
                                $this->stringifyValue($expected),
                                $this->stringifyValue($tags)
                            ),
                    ];
                }

                if ($operator === 'not_equals') {
                    $result = ! in_array($expected, $tags, true);
                    return [
                        'result' => $result,
                        'reason' => $result
                            ? null
                            : sprintf(
                                'Condition failed: %s %s %s (current: %s)',
                                $fieldLabel,
                                $operatorLabel,
                                $this->stringifyValue($expected),
                                $this->stringifyValue($tags)
                            ),
                    ];
                }

                return ['result' => false, 'reason' => sprintf('Unsupported operator "%s" for %s.', $operator, $fieldLabel)];
            }
            case 'visit_count':
            case 'days_since_last_attended': {
                $actual = Arr::get($context, $field);
                return $this->evaluateNumericRule($actual, $value, $operator, $fieldLabel, $operatorLabel);
            }
            case 'has_family_assignment': {
                $actual = Arr::get($context, $field);
                $expectedBool = $this->normalizeBoolean($value);
                if ($expectedBool === null) {
                    return ['result' => false, 'reason' => 'Invalid boolean value for automation rule.'];
                }
                if ($operator === 'equals') {
                    $result = (bool) $actual === $expectedBool;
                } elseif ($operator === 'not_equals') {
                    $result = (bool) $actual !== $expectedBool;
                } else {
                    return ['result' => false, 'reason' => sprintf('Unsupported operator "%s" for %s.', $operator, $fieldLabel)];
                }

                return [
                    'result' => $result,
                    'reason' => $result
                        ? null
                        : sprintf(
                            'Condition failed: %s %s %s (current: %s)',
                            $fieldLabel,
                            $operatorLabel,
                            $this->stringifyValue($expectedBool),
                            $this->stringifyValue((bool) $actual)
                        ),
                ];
            }
            default:
                return ['result' => false, 'reason' => sprintf('Unknown rule field "%s".', $field)];
        }
    }

    protected function stepHasRules(VisitorWorkflowStep $step): bool
    {
        $metadata = $step->metadata;
        if (! is_array($metadata)) {
            return false;
        }

        $rules = Arr::get($metadata, 'rules');

        return is_array($rules) && count($rules) > 0;
    }

    protected function logStepSkipped(VisitorFollowup $followup, VisitorWorkflowStep $step, string $reason): void
    {
        VisitorFollowupLog::create([
            'followup_id' => $followup->id,
            'step_id' => $step->id,
            'status' => 'skipped',
            'channel' => $step->channel,
            'run_at' => Carbon::now(),
            'notes' => $reason,
        ]);
    }

    protected function evaluateStringRule(?string $actual, mixed $value, string $operator, string $fieldLabel, string $operatorLabel): array
    {
        $expected = is_string($value) ? $value : (string) $value;
        $actualNormalized = $actual !== null ? mb_strtolower($actual) : null;
        $expectedNormalized = mb_strtolower($expected);

        switch ($operator) {
            case 'equals':
                $result = $actualNormalized === $expectedNormalized;
                break;
            case 'not_equals':
                $result = $actualNormalized !== $expectedNormalized;
                break;
            case 'contains':
                $result = $actualNormalized !== null && str_contains($actualNormalized, $expectedNormalized);
                break;
            default:
                return ['result' => false, 'reason' => sprintf('Unsupported operator "%s" for %s.', $operator, $fieldLabel)];
        }

        return [
            'result' => $result,
            'reason' => $result
                ? null
                : sprintf(
                    'Condition failed: %s %s %s (current: %s)',
                    $fieldLabel,
                    $operatorLabel,
                    $this->stringifyValue($expected),
                    $this->stringifyValue($actual)
                ),
        ];
    }

    protected function evaluateNumericRule(mixed $actual, mixed $value, string $operator, string $fieldLabel, string $operatorLabel): array
    {
        if (! is_numeric($value)) {
            return ['result' => false, 'reason' => sprintf('Invalid numeric value for %s.', $fieldLabel)];
        }

        if ($actual === null) {
            return [
                'result' => false,
                'reason' => sprintf(
                    'Condition failed: %s %s %s (current: %s)',
                    $fieldLabel,
                    $operatorLabel,
                    $this->stringifyValue($value),
                    'none'
                ),
            ];
        }

        $actualNumber = (float) $actual;
        $expectedNumber = (float) $value;

        switch ($operator) {
            case 'equals':
                $result = $actualNumber === $expectedNumber;
                break;
            case 'greater_than':
                $result = $actualNumber > $expectedNumber;
                break;
            case 'less_than':
                $result = $actualNumber < $expectedNumber;
                break;
            case 'not_equals':
                $result = $actualNumber !== $expectedNumber;
                break;
            default:
                return ['result' => false, 'reason' => sprintf('Unsupported operator "%s" for %s.', $operator, $fieldLabel)];
        }

        return [
            'result' => $result,
            'reason' => $result
                ? null
                : sprintf(
                    'Condition failed: %s %s %s (current: %s)',
                    $fieldLabel,
                    $operatorLabel,
                    $this->stringifyValue($expectedNumber),
                    $this->stringifyValue($actualNumber)
                ),
        ];
    }

    protected function normalizeBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        if (is_string($value)) {
            $normalized = mb_strtolower($value);
            if (in_array($normalized, ['true', '1', 'yes'], true)) {
                return true;
            }
            if (in_array($normalized, ['false', '0', 'no'], true)) {
                return false;
            }
        }

        return null;
    }

    protected function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return 'none';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value)) {
            return implode(', ', array_map([$this, 'stringifyValue'], $value));
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return (string) $value;
    }

    protected const RULE_FIELD_LABELS = [
        'member_status' => 'Member status',
        'member_stage' => 'Member stage',
        'member_tags' => 'Member tags',
        'visit_count' => 'Total visits',
        'days_since_last_attended' => 'Days since last attendance',
        'has_family_assignment' => 'Has family assignment',
    ];

    protected const RULE_OPERATOR_LABELS = [
        'equals' => 'is',
        'not_equals' => 'is not',
        'contains' => 'contains',
        'greater_than' => 'is greater than',
        'less_than' => 'is less than',
    ];
}
