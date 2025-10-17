<?php

namespace App\Console\Commands;

use App\Models\Family;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendFamilyRemindersCommand extends Command
{
    protected $signature = 'families:send-reminders';

    protected $description = 'Send follow-up reminders for families missing contacts or celebrating anniversaries.';

    public function handle(NotificationService $notificationService): int
    {
        Tenant::query()->with(['users.roles', 'families.members'])->chunkById(25, function ($tenants) use ($notificationService) {
            foreach ($tenants as $tenant) {
                $recipient = $tenant->users()
                    ->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))
                    ->value('email')
                    ?: $tenant->users()->value('email');

                if (! $recipient) {
                    continue;
                }

                $families = $tenant->families()->with('members')->get();

                $missingPrimary = $families->filter(function (Family $family) {
                    return ! $family->members->contains(fn ($member) => (bool) ($member->pivot->is_primary_contact ?? false));
                });

                $missingEmergency = $families->filter(function (Family $family) {
                    return ! $family->members->contains(fn ($member) => (bool) ($member->pivot->is_emergency_contact ?? false));
                });

                $anniversaryCutoff = Carbon::now($tenant->timezone ?? 'UTC')->addDays(14);
                $anniversaries = $families
                    ->filter(fn (Family $family) => $family->created_at !== null)
                    ->map(function (Family $family) use ($anniversaryCutoff) {
                        $next = $family->created_at->copy()->setTimezone($anniversaryCutoff->timezone);
                        $next->year($anniversaryCutoff->year);
                        if ($next->lt($anniversaryCutoff->copy()->startOfDay())) {
                            $next->addYear();
                        }

                        return [
                            'family' => $family,
                            'next' => $next,
                        ];
                    })
                    ->filter(fn ($data) => $data['next']->diffInDays($anniversaryCutoff, false) >= 0 && $data['next']->diffInDays($anniversaryCutoff, false) <= 14)
                    ->sortBy('next')
                    ->take(10);

                if ($missingPrimary->isEmpty() && $missingEmergency->isEmpty() && $anniversaries->isEmpty()) {
                    continue;
                }

                $lines = [];
                if ($missingPrimary->isNotEmpty()) {
                    $lines[] = 'Households missing a primary contact:';
                    foreach ($missingPrimary->take(10) as $family) {
                        $lines[] = '- ' . $family->family_name;
                    }
                    if ($missingPrimary->count() > 10) {
                        $lines[] = '...plus ' . ($missingPrimary->count() - 10) . ' more.';
                    }
                    $lines[] = '';
                }

                if ($missingEmergency->isNotEmpty()) {
                    $lines[] = 'Households without an emergency contact:';
                    foreach ($missingEmergency->take(10) as $family) {
                        $lines[] = '- ' . $family->family_name;
                    }
                    if ($missingEmergency->count() > 10) {
                        $lines[] = '...plus ' . ($missingEmergency->count() - 10) . ' more.';
                    }
                    $lines[] = '';
                }

                if ($anniversaries->isNotEmpty()) {
                    $lines[] = 'Upcoming family anniversaries:';
                    foreach ($anniversaries as $entry) {
                        /** @var Family $family */
                        $family = $entry['family'];
                        /** @var Carbon $date */
                        $date = $entry['next'];
                        $lines[] = sprintf('- %s on %s', $family->family_name, $date->toFormattedDateString());
                    }
                    $lines[] = '';
                }

                $body = implode("\n", array_filter($lines));

                $notificationService->queue([
                    'tenant_id' => $tenant->id,
                    'channel' => 'email',
                    'recipient' => $recipient,
                    'subject' => 'Family follow-up reminders',
                    'body' => $body,
                ]);
            }
        });

        $this->info('Family reminders dispatched.');

        return self::SUCCESS;
    }
}
