<?php

namespace App\Console\Commands;

use App\Models\VolunteerSignup;
use App\Services\VolunteerPipelineService;
use App\Support\VolunteerSignupStage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendVolunteerSignupFollowupsCommand extends Command
{
    protected $signature = 'volunteers:send-followups {--tenant= : Limit reminders to a specific tenant ID} {--limit= : Maximum number of reminders to process}';

    protected $description = 'Send follow-up reminders for volunteer signups that are due.';

    public function handle(VolunteerPipelineService $pipelineService): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $limitOption = $this->option('limit');
        $limit = $limitOption !== null ? max((int) $limitOption, 0) : null;

        $query = VolunteerSignup::query()
            ->whereNotIn('stage', [VolunteerSignupStage::READY, VolunteerSignupStage::INACTIVE])
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', Carbon::now())
            ->orderBy('follow_up_at')
            ->when($tenantId, fn ($builder) => $builder->where('tenant_id', $tenantId));

        $processed = 0;

        $query->chunkById(50, function ($signups) use ($pipelineService, $limit, &$processed) {
            foreach ($signups as $signup) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }

                $pipelineService->sendFollowUpReminder($signup);
                $processed++;
            }

            return true;
        });

        $this->info(sprintf('Dispatched %d volunteer follow-up reminder(s).', $processed));

        return self::SUCCESS;
    }
}
