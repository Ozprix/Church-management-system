<?php

namespace App\Services;

use App\Jobs\SendMembershipStageReminderJob;
use App\Models\Member;
use App\Models\MemberProcessRun;
use App\Models\MembershipProcess;
use App\Models\MembershipProcessLog;
use App\Models\MembershipProcessStage;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MembershipProcessService
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function createProcess(array $attributes): MembershipProcess
    {
        return DB::transaction(function () use ($attributes): MembershipProcess {
            $stages = Arr::pull($attributes, 'stages', []);

            /** @var MembershipProcess $process */
            $process = MembershipProcess::create($attributes);

            $this->syncStages($process, $stages);

            return $process->fresh('stages');
        });
    }

    public function updateProcess(MembershipProcess $process, array $attributes): MembershipProcess
    {
        return DB::transaction(function () use ($process, $attributes): MembershipProcess {
            $stages = Arr::pull($attributes, 'stages', null);

            if (! empty($attributes)) {
                $process->fill($attributes);
                $process->save();
            }

            if (is_array($stages)) {
                $this->syncStages($process, $stages);
            }

            return $process->fresh('stages');
        });
    }

    protected function syncStages(MembershipProcess $process, array $stages): void
    {
        $existing = $process->stages()->get()->keyBy('id');
        $order = 1;
        $stageIdsToKeep = [];

        foreach ($stages as $stageData) {
            $stageId = Arr::get($stageData, 'id');

            $payload = [
                'key' => Arr::get($stageData, 'key', Str::slug(Arr::get($stageData, 'name', 'stage-' . $order))),
                'name' => Arr::get($stageData, 'name'),
                'step_order' => $order++,
                'entry_actions' => Arr::get($stageData, 'entry_actions'),
                'exit_actions' => Arr::get($stageData, 'exit_actions'),
                'reminder_minutes' => Arr::get($stageData, 'reminder_minutes'),
                'reminder_template_id' => Arr::get($stageData, 'reminder_template_id'),
                'metadata' => Arr::get($stageData, 'metadata'),
            ];

            if ($stageId && $existing->has($stageId)) {
                /** @var MembershipProcessStage $stage */
                $stage = $existing->get($stageId);
                $stage->fill($payload);
                $stage->save();
                $stageIdsToKeep[] = $stage->id;
            } else {
                $stage = $process->stages()->create($payload);
                $stageIdsToKeep[] = $stage->id;
            }
        }

        if (! empty($stageIdsToKeep)) {
            $process->stages()->whereNotIn('id', $stageIdsToKeep)->delete();
        }
    }

    public function startProcess(Member $member, MembershipProcess $process): MemberProcessRun
    {
        return DB::transaction(function () use ($member, $process): MemberProcessRun {
            $run = MemberProcessRun::query()->updateOrCreate(
                [
                    'tenant_id' => $member->tenant_id,
                    'member_id' => $member->id,
                    'process_id' => $process->id,
                ],
                [
                    'status' => 'in_progress',
                    'started_at' => now(),
                    'metadata' => null,
                ]
            );

            $firstStage = $process->stages()->orderBy('step_order')->first();

            if ($firstStage) {
                $run->current_stage_id = $firstStage->id;
                $run->save();

                $this->logStageTransition($run, $firstStage, 'entered', 'Process started');
                $this->handleStageEntry($run, $firstStage);
            }

            return $run->fresh(['currentStage', 'process']);
        });
    }

    public function advance(MemberProcessRun $run, ?MembershipProcessStage $nextStage, ?string $note = null): MemberProcessRun
    {
        return DB::transaction(function () use ($run, $nextStage, $note): MemberProcessRun {
            if ($nextStage) {
                if ($run->currentStage) {
                    $this->handleStageExit($run, $run->currentStage, $note);
                    $this->logStageTransition($run, $run->currentStage, 'completed', $note ?? 'Stage completed');
                }
                $run->current_stage_id = $nextStage->id;
                $run->status = 'in_progress';
                $run->save();
                $this->logStageTransition($run, $nextStage, 'entered', 'Stage entered');
                $this->handleStageEntry($run, $nextStage);
            } else {
                if ($run->currentStage) {
                    $this->handleStageExit($run, $run->currentStage, $note);
                    $this->logStageTransition($run, $run->currentStage, 'completed', $note ?? 'Process completed');
                }
                $run->status = 'completed';
                $run->completed_at = now();
                $run->current_stage_id = null;
                $run->save();
            }

            return $run->fresh(['currentStage', 'process']);
        });
    }

    public function halt(MemberProcessRun $run, ?string $reason = null): MemberProcessRun
    {
        $run->status = 'halted';
        $run->halted_at = now();
        $run->save();

        $this->logStageTransition($run, $run->currentStage, 'halted', $reason);

        return $run->fresh(['currentStage', 'process']);
    }

    protected function logStageTransition(MemberProcessRun $run, ?MembershipProcessStage $stage, string $status, ?string $notes = null): void
    {
        MembershipProcessLog::create([
            'process_run_id' => $run->id,
            'stage_id' => $stage?->id,
            'actor_id' => auth()->id(),
            'status' => $status,
            'notes' => $notes,
            'metadata' => null,
            'logged_at' => now(),
        ]);
    }

    protected function handleStageEntry(MemberProcessRun $run, MembershipProcessStage $stage): void
    {
        $this->executeActions($run, $stage, $stage->entry_actions ?? []);

        if ($stage->reminder_minutes && $stage->reminder_template_id) {
            SendMembershipStageReminderJob::dispatch($run->id, $stage->id)
                ->delay(now()->addMinutes($stage->reminder_minutes));
        }
    }

    protected function handleStageExit(MemberProcessRun $run, MembershipProcessStage $stage, ?string $note = null): void
    {
        $this->executeActions($run, $stage, $stage->exit_actions ?? []);
    }

    protected function executeActions(MemberProcessRun $run, MembershipProcessStage $stage, array $actions): void
    {
        foreach ($actions as $action) {
            $type = Arr::get($action, 'type');

            if (! $type) {
                continue;
            }

            match ($type) {
                'notify_member' => $this->sendMemberNotification($run, $action),
                default => null,
            };
        }
    }

    protected function sendMemberNotification(MemberProcessRun $run, array $action): void
    {
        $templateId = Arr::get($action, 'template_id');
        if (! $templateId) {
            return;
        }

        $channel = Arr::get($action, 'channel', 'email');

        $payload = Arr::get($action, 'payload', []);
        $payload['process'] = $run->process?->name;
        $payload['member'] = $run->member?->only(['first_name', 'last_name']);

        $this->notificationService->queue([
            'tenant_id' => $run->tenant_id,
            'member_id' => $run->member_id,
            'notification_template_id' => $templateId,
            'channel' => $channel,
            'payload' => $payload,
        ]);
    }
}
