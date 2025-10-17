<?php

namespace App\Jobs;

use App\Jobs\Concerns\DispatchesForTenant;
use App\Models\MemberProcessRun;
use App\Models\MembershipProcessStage;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMembershipStageReminderJob implements ShouldQueue
{
    use DispatchesForTenant;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly int $runId, private readonly int $stageId)
    {
    }

    public function getRunId(): int
    {
        return $this->runId;
    }

    public function getStageId(): int
    {
        return $this->stageId;
    }

    public function handle(NotificationService $notificationService): void
    {
        /** @var MemberProcessRun|null $run */
        $run = MemberProcessRun::query()
            ->with(['member', 'process'])
            ->find($this->runId);

        if (! $run || $run->status !== 'in_progress' || $run->current_stage_id !== $this->stageId) {
            return;
        }

        /** @var MembershipProcessStage|null $stage */
        $stage = MembershipProcessStage::query()->find($this->stageId);

        if (! $stage || ! $stage->reminder_template_id) {
            return;
        }

        $notificationService->queue([
            'tenant_id' => $run->tenant_id,
            'member_id' => $run->member_id,
            'notification_template_id' => $stage->reminder_template_id,
            'channel' => $stage->reminderTemplate?->channel ?? 'email',
            'payload' => [
                'process' => $run->process?->name,
                'stage' => $stage->name,
            ],
        ]);
    }
}
