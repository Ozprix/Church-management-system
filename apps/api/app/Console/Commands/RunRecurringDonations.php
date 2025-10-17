<?php

namespace App\Console\Commands;

use App\Services\RecurringDonationService;
use Illuminate\Console\Command;

class RunRecurringDonations extends Command
{
    protected $signature = 'finance:recurring-run';

    protected $description = 'Dispatch due recurring donation schedules.';

    public function __construct(private readonly RecurringDonationService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->service->scheduleDue();

        $this->info("Dispatched {$count} schedules for processing.");

        return self::SUCCESS;
    }
}
