<?php

namespace App\Jobs;

use App\Jobs\Concerns\DispatchesForTenant;
use App\Models\Pledge;
use App\Services\NotificationService;
use App\Support\PledgeReminderCadence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SendPledgeReminderJob implements ShouldQueue
{
    use DispatchesForTenant;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $pledgeId)
    {
    }

    public function getPledgeId(): int
    {
        return $this->pledgeId;
    }

    public function handle(NotificationService $notificationService): void
    {
        /** @var Pledge|null $pledge */
        $pledge = Pledge::query()
            ->with(['member.contacts', 'tenant', 'fund'])
            ->find($this->pledgeId);

        if (! $pledge || ! $pledge->reminder_enabled || $pledge->status !== 'active') {
            return;
        }

        if (! $pledge->member_id || empty($pledge->reminder_cadence)) {
            return;
        }

        if ($this->isPledgeFulfilled($pledge)) {
            $pledge->forceFill([
                'reminder_enabled' => false,
                'reminder_cadence' => null,
                'next_reminder_at' => null,
            ])->save();

            return;
        }

        $nowTenant = Carbon::now($pledge->tenant?->timezone ?? config('app.timezone'));

        if ($pledge->next_reminder_at instanceof Carbon && $pledge->next_reminder_at->gt($nowTenant)) {
            return;
        }

        $this->queueNotificationPair($notificationService, $pledge, $nowTenant);

        $nextReminder = $this->calculateNextReminder($pledge, $nowTenant);

        $pledge->forceFill([
            'last_reminder_sent_at' => $nowTenant->copy()->setTimezone(config('app.timezone')),
            'next_reminder_at' => $nextReminder->setTimezone(config('app.timezone')),
        ])->save();
    }

    protected function queueNotificationPair(NotificationService $notificationService, Pledge $pledge, Carbon $nowTenant): void
    {
        $tenantName = $pledge->tenant?->name ?? 'your church';
        $fundName = $pledge->fund?->name ?? 'general fund';
        $outstanding = $this->calculateOutstandingAmount($pledge);
        $currency = $pledge->currency ?? 'USD';

        $friendlyAmount = number_format((float) $pledge->amount, 2);
        $friendlyOutstanding = number_format($outstanding, 2);

        $payload = [
            'pledge_id' => $pledge->id,
            'fund' => $fundName,
            'tenant' => $tenantName,
            'amount' => $pledge->amount,
            'fulfilled_amount' => $pledge->fulfilled_amount,
            'outstanding_amount' => $outstanding,
            'currency' => $currency,
            'reminder_sent_at' => $nowTenant->toIso8601String(),
        ];

        $emailLines = [
            "Hi {$pledge->member?->first_name},",
            "This is a friendly reminder about your pledge of {$currency} {$friendlyAmount} supporting {$fundName}.",
            "Outstanding balance: {$currency} {$friendlyOutstanding}.",
        ];

        if ($pledge->notes) {
            $emailLines[] = "Pledge note: {$pledge->notes}";
        }

        $emailLines[] = "Thank you for partnering with {$tenantName}!";

        $emailBody = implode("\n\n", $emailLines);

        $notificationService->queue([
            'tenant_id' => $pledge->tenant_id,
            'member_id' => $pledge->member_id,
            'channel' => 'email',
            'subject' => "{$tenantName} pledge reminder",
            'body' => $emailBody,
            'payload' => $payload,
        ]);

        $smsBody = "{$tenantName}: Pledge reminder for {$fundName}. Outstanding {$currency} {$friendlyOutstanding}. Thank you!";

        $notificationService->queue([
            'tenant_id' => $pledge->tenant_id,
            'member_id' => $pledge->member_id,
            'channel' => 'sms',
            'body' => $smsBody,
            'payload' => $payload,
        ]);
    }

    protected function calculateNextReminder(Pledge $pledge, Carbon $reference): Carbon
    {
        $cadence = $pledge->reminder_cadence ?? PledgeReminderCadence::MONTHLY;
        $base = $pledge->next_reminder_at instanceof Carbon
            ? $pledge->next_reminder_at->copy()->setTimezone($reference->getTimezone())
            : $reference->copy();

        if ($base->gt($reference)) {
            return $base;
        }

        $next = $base->copy();

        do {
            $next = match ($cadence) {
                PledgeReminderCadence::DAILY => $next->addDay(),
                PledgeReminderCadence::WEEKLY => $next->addWeek(),
                PledgeReminderCadence::QUARTERLY => $next->addMonths(3),
                default => $next->addMonth(),
            };
        } while ($next->lte($reference));

        return $next;
    }

    protected function calculateOutstandingAmount(Pledge $pledge): float
    {
        $amount = (float) $pledge->amount;
        $fulfilled = (float) ($pledge->fulfilled_amount ?? 0.0);

        return max(round($amount - $fulfilled, 2), 0.0);
    }

    protected function isPledgeFulfilled(Pledge $pledge): bool
    {
        $amount = (float) $pledge->amount;
        $fulfilled = (float) ($pledge->fulfilled_amount ?? 0.0);

        return $fulfilled >= $amount && $amount > 0;
    }
}
