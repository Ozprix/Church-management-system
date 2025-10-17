<?php

namespace App\Jobs;

use App\Jobs\Concerns\DispatchesForTenant;
use App\Models\RecurringDonationSchedule;
use App\Services\RecurringDonationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessRecurringDonationJob implements ShouldQueue
{
    use DispatchesForTenant;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly int $scheduleId)
    {
    }

    public function handle(RecurringDonationService $service): void
    {
        $schedule = RecurringDonationSchedule::query()->find($this->scheduleId);

        if (! $schedule || $schedule->status !== 'active') {
            return;
        }

        $service->processSchedule($schedule);
    }
}
