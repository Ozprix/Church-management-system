<?php

namespace App\Jobs;

use App\Jobs\Concerns\DispatchesForTenant;
use App\Models\VisitorFollowup;
use App\Services\VisitorAutomationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessVisitorFollowupJob implements ShouldQueue
{
    use DispatchesForTenant;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly int $followupId)
    {
    }

    public function handle(VisitorAutomationService $automationService): void
    {
        $followup = VisitorFollowup::query()->with(['workflow.steps', 'member'])->find($this->followupId);

        if (! $followup) {
            return;
        }

        $automationService->runFollowupStep($followup);
    }
}
