<?php

namespace App\Console\Commands;

use App\Models\MemberAnalyticsReport;
use App\Services\Analytics\MemberAnalyticsService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RunMemberAnalyticsReportsCommand extends Command
{
    protected $signature = 'members:run-saved-reports';

    protected $description = 'Evaluate scheduled member analytics reports and deliver outputs.';

    public function handle(MemberAnalyticsService $analyticsService, NotificationService $notificationService): int
    {
        $now = Carbon::now();

        MemberAnalyticsReport::query()
            ->where('frequency', '!=', 'none')
            ->with('tenant', 'owner')
            ->chunkById(25, function ($reports) use ($analyticsService, $notificationService, $now) {
                foreach ($reports as $report) {
                    if (! $report->tenant) {
                        continue;
                    }

                    if (! $this->isDue($report, $now)) {
                        continue;
                    }

                    $filters = $analyticsService->parseFilters($report->filters ?? []);
                    $metrics = $analyticsService->buildMetrics($filters);

                    if (in_array($report->channel, ['email', 'both'], true)) {
                        $recipient = $report->email_recipient
                            ?? $report->owner?->email
                            ?? $report->tenant->users()->value('email');

                        if ($recipient) {
                            $summary = sprintf(
                                "Members: %d\nWithout family: %d\nStale profiles: %d",
                                $metrics['totals']['members'] ?? 0,
                                $metrics['totals']['members_without_family'] ?? 0,
                                $metrics['totals']['stale_profiles'] ?? 0
                            );

                            $notificationService->queue([
                                'tenant_id' => $report->tenant_id,
                                'channel' => 'email',
                                'recipient' => $recipient,
                                'subject' => 'Scheduled member analytics report: ' . $report->name,
                                'body' => $summary,
                            ]);
                        }
                    }

                    // Future: handle download/both by storing snapshot

                    $report->forceFill(['last_run_at' => $now])->save();
                }
            });

        $this->info('Scheduled member analytics reports processed.');

        return self::SUCCESS;
    }

    private function isDue(MemberAnalyticsReport $report, Carbon $now): bool
    {
        if (! $report->isScheduled()) {
            return false;
        }

        if (! $report->last_run_at) {
            return true;
        }

        return match ($report->frequency) {
            'daily' => $report->last_run_at->diffInDays($now) >= 1,
            'weekly' => $report->last_run_at->diffInWeeks($now) >= 1,
            'monthly' => $report->last_run_at->diffInMonths($now) >= 1,
            default => false,
        };
    }
}
