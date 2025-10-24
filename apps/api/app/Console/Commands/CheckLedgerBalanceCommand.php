<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Finance\LedgerReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckLedgerBalanceCommand extends Command
{
    protected $signature = 'finance:ledger-check {--tenant= : Limit to a specific tenant ID} {--month= : Month to analyze (YYYY-MM)}';

    protected $description = 'Verify double-entry ledger balance for the requested month.';

    public function handle(LedgerReconciliationService $reconciliationService): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $monthOption = $this->option('month');

        $month = $monthOption ? Carbon::createFromFormat('Y-m', $monthOption)->startOfMonth() : Carbon::now()->startOfMonth();

        $tenants = Tenant::query()
            ->when($tenantId, fn ($query) => $query->where('id', $tenantId))
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found to reconcile.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($tenants as $tenant) {
            $trialBalance = $reconciliationService->buildMonthlyTrialBalance($tenant->id, $month);
            $balanced = $trialBalance['totals']['balanced'] ?? false;

            if ($balanced) {
                $this->info(sprintf(
                    'Tenant %s (%d) balanced for %s (Debits %s / Credits %s).',
                    $tenant->name,
                    $tenant->id,
                    $month->format('Y-m'),
                    $trialBalance['totals']['debits'],
                    $trialBalance['totals']['credits']
                ));
            } else {
                $this->error(sprintf(
                    'Tenant %s (%d) OUT OF BALANCE for %s (Debits %s / Credits %s).',
                    $tenant->name,
                    $tenant->id,
                    $month->format('Y-m'),
                    $trialBalance['totals']['debits'],
                    $trialBalance['totals']['credits']
                ));
                $failures++;
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
