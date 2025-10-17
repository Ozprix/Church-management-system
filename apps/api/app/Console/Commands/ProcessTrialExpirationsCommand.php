<?php

namespace App\Console\Commands;

use App\Jobs\ProcessTrialExpirationsJob;
use Illuminate\Console\Command;

class ProcessTrialExpirationsCommand extends Command
{
    protected $signature = 'billing:process-trials';

    protected $description = 'Process tenant trial expirations and update subscription status.';

    public function handle(): int
    {
        ProcessTrialExpirationsJob::dispatch();
        $this->info('Trial expiration job dispatched.');

        return self::SUCCESS;
    }
}
