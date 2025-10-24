<?php

namespace App\Console\Commands;

use App\Jobs\SendPledgeReminderJob;
use App\Models\Pledge;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendPledgeRemindersCommand extends Command
{
    protected $signature = 'pledges:send-reminders {--tenant= : Limit reminders to a specific tenant ID} {--limit= : Maximum number of reminders to queue}';

    protected $description = 'Queue reminder notifications for pledges that are due.';

    public function handle(): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $limitOption = $this->option('limit');
        $limit = $limitOption !== null ? max((int) $limitOption, 0) : null;

        $now = Carbon::now();
        $dispatched = 0;

        $query = Pledge::query()
            ->where('reminder_enabled', true)
            ->whereNotNull('reminder_cadence')
            ->where('status', 'active')
            ->whereNotNull('member_id')
            ->whereNull('deleted_at')
            ->when($tenantId, fn ($builder) => $builder->where('tenant_id', $tenantId))
            ->where(function ($builder) use ($now) {
                $builder->whereNull('next_reminder_at')
                    ->orWhere('next_reminder_at', '<=', $now);
            });

        $query->chunkById(50, function ($pledges) use (&$dispatched, $limit) {
            foreach ($pledges as $pledge) {
                if ($limit !== null && $dispatched >= $limit) {
                    return false;
                }

                SendPledgeReminderJob::dispatchForTenant($pledge->tenant_id, $pledge->id);
                $dispatched++;
            }

            return true;
        });

        $this->info(sprintf('Queued %d pledge reminder(s).', $dispatched));

        return self::SUCCESS;
    }
}
