<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Tenant;
use App\Services\Analytics\MemberAnalyticsService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendMemberStaleProfilesAlertCommand extends Command
{
    protected $signature = 'members:send-stale-alerts';

    protected $description = 'Send alerts when stale member profile counts exceed the configured threshold.';

    public function handle(NotificationService $notificationService, MemberAnalyticsService $analyticsService): int
    {
        $threshold = (int) config('analytics.member_stale_threshold', 10);
        $staleSince = Carbon::now()->subMonths(6);

        Tenant::query()->with('users.roles')->chunkById(25, function ($tenants) use ($notificationService, $threshold, $staleSince, $analyticsService) {
            foreach ($tenants as $tenant) {
                $recipient = $tenant->users()
                    ->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))
                    ->value('email')
                    ?: $tenant->users()->value('email');

                if (! $recipient) {
                    continue;
                }

                $filters = $analyticsService->parseFilters([]);
                $filters['joined_from'] = null;
                $filters['joined_to'] = null;

                $baseQuery = Member::query()->where('tenant_id', $tenant->id);
                $filteredQuery = $analyticsService->applyFilters($baseQuery, $filters);
                $staleCount = (clone $filteredQuery)
                    ->where('updated_at', '<', $staleSince)
                    ->count();

                if ($staleCount < $threshold) {
                    continue;
                }

                $notificationService->queue([
                    'tenant_id' => $tenant->id,
                    'channel' => 'email',
                    'recipient' => $recipient,
                    'subject' => 'Member profile refresh needed',
                    'body' => sprintf(
                        'There are currently %d member profiles that have not been updated in the past six months. Consider reviewing stale records and reaching out for updates.',
                        $staleCount
                    ),
                ]);
            }
        });

        $this->info('Stale profile alerts evaluated.');

        return self::SUCCESS;
    }
}
