<?php

namespace App\Services;

use App\Jobs\ProcessRecurringDonationJob;
use App\Models\Donation;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\RecurringDonationAttempt;
use App\Models\RecurringDonationSchedule;
use App\Support\Tenancy\TenantManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringDonationService
{
    public function __construct(private readonly TenantManager $tenantManager, private readonly FinanceService $financeService)
    {
    }

    public function createSchedule(array $attributes): RecurringDonationSchedule
    {
        $tenantId = $attributes['tenant_id'] ?? optional($this->tenantManager->getTenant())->id;

        if (! empty($attributes['member_id'])) {
            Member::query()->where('tenant_id', $tenantId)->findOrFail($attributes['member_id']);
        }

        if (! empty($attributes['payment_method_id'])) {
            PaymentMethod::query()->where('tenant_id', $tenantId)->findOrFail($attributes['payment_method_id']);
        }

        $schedule = RecurringDonationSchedule::create($attributes + [
            'tenant_id' => $tenantId,
            'next_run_at' => $this->calculateNextRunAt($attributes['starts_on'], $attributes['frequency']),
        ]);

        ProcessRecurringDonationJob::dispatch($schedule->id)->delay($schedule->next_run_at ?? Carbon::now());

        return $schedule->fresh();
    }

    public function updateSchedule(RecurringDonationSchedule $schedule, array $attributes): RecurringDonationSchedule
    {
        if (array_key_exists('starts_on', $attributes) || array_key_exists('frequency', $attributes)) {
            $startsOn = $attributes['starts_on'] ?? $schedule->starts_on;
            $frequency = $attributes['frequency'] ?? $schedule->frequency;
            $schedule->next_run_at = $this->calculateNextRunAt($startsOn, $frequency);
        }

        $schedule->fill($attributes);
        $schedule->save();

        return $schedule->fresh();
    }

    public function processSchedule(RecurringDonationSchedule $schedule): RecurringDonationAttempt
    {
        return DB::transaction(function () use ($schedule) {
            $attempt = RecurringDonationAttempt::create([
                'schedule_id' => $schedule->id,
                'status' => 'processing',
                'amount' => $schedule->amount,
                'currency' => $schedule->currency,
            ]);

            try {
                $donation = $this->financeService->recordDonation([
                    'tenant_id' => $schedule->tenant_id,
                    'member_id' => $schedule->member_id,
                    'payment_method_id' => $schedule->payment_method_id,
                    'amount' => $schedule->amount,
                    'currency' => $schedule->currency,
                    'status' => 'succeeded',
                    'provider' => 'recurring',
                    'metadata' => [
                        'schedule_id' => $schedule->id,
                        'attempt_id' => $attempt->id,
                    ],
                ]);

                $attempt->donation_id = $donation->id;
                $attempt->status = 'succeeded';
                $attempt->processed_at = Carbon::now();
                $attempt->save();
            } catch (\Throwable $exception) {
                $attempt->status = 'failed';
                $attempt->failure_reason = $exception->getMessage();
                $attempt->processed_at = Carbon::now();
                $attempt->save();
            }

            if ($schedule->status === 'active') {
                $schedule->next_run_at = $this->calculateNextRunAt(Carbon::now(), $schedule->frequency);
                $schedule->save();
            }

            return $attempt->fresh('donation');
        });
    }

    public function calculateNextRunAt($fromDate, string $frequency): Carbon
    {
        $date = Carbon::parse($fromDate);

        return match ($frequency) {
            'weekly' => $date->copy()->addWeek(),
            'biweekly' => $date->copy()->addWeeks(2),
            'monthly' => $date->copy()->addMonth(),
            'quarterly' => $date->copy()->addQuarter(),
            'annually' => $date->copy()->addYear(),
            default => $date->copy()->addMonth(),
        };
    }

    public function scheduleDue(): int
    {
        $query = RecurringDonationSchedule::query()
            ->where('status', 'active')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', Carbon::now());

        if ($this->tenantManager->hasTenant()) {
            $query->where('tenant_id', $this->tenantManager->getTenant()->getKey());
        }

        $schedules = $query->limit(100)->get();

        foreach ($schedules as $schedule) {
            ProcessRecurringDonationJob::dispatch($schedule->id);
        }

        return $schedules->count();
    }
}
