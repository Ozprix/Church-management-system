<?php

namespace App\Console\Commands;

use App\Services\VisitorAutomationService;
use Illuminate\Console\Command;

class RunVisitorFollowups extends Command
{
    protected $signature = 'visitor:followups-run';

    protected $description = 'Dispatch due visitor followup actions.';

    public function __construct(private readonly VisitorAutomationService $automationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->automationService->processDueFollowups();

        $this->info("Dispatched {$count} followups for processing.");

        return self::SUCCESS;
    }
}
