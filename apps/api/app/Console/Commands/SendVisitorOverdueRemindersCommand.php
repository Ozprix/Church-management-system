<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\VisitorFollowup;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendVisitorOverdueRemindersCommand extends Command
{
    protected $signature = 'visitors:send-overdue-reminders';

    protected $description = 'Email staff a digest of overdue visitor follow-ups.';

    public function handle(NotificationService $notificationService): int
    {
        $now = Carbon::now();

        Tenant::query()->with('users.roles')->chunkById(25, function ($tenants) use ($notificationService, $now) {
            foreach ($tenants as $tenant) {
                $recipient = $tenant->users()
                    ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['visitor_manager', 'admin']))
                    ->value('email');

                if (! $recipient) {
                    continue;
                }

                $overdue = VisitorFollowup::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->whereNotNull('next_run_at')
                    ->where('next_run_at', '<', $now)
                    ->with('member')
                    ->orderBy('next_run_at')
                    ->take(20)
                    ->get();

                if ($overdue->isEmpty()) {
                    continue;
                }

                $lines = ['The following visitor follow-ups are overdue:', ''];
                foreach ($overdue as $followup) {
                    $lines[] = sprintf(
                        '- %s (workflow: %s, due %s)',
                        optional($followup->member)->first_name . ' ' . optional($followup->member)->last_name,
                        optional($followup->workflow)->name ?? 'unknown',
                        optional($followup->next_run_at)->diffForHumans()
                    );
                }

                if ($overdue->count() === 20) {
                    $lines[] = '...additional follow-ups pending.';
                }

                $notificationService->queue([
                    'tenant_id' => $tenant->id,
                    'channel' => 'email',
                    'recipient' => $recipient,
                    'subject' => 'Visitor follow-ups overdue',
                    'body' => implode("\n", $lines),
                ]);
            }
        });

        $this->info('Visitor overdue reminders dispatched.');

        return self::SUCCESS;
    }
}
