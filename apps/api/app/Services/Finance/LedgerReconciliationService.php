<?php

namespace App\Services\Finance;

use App\Models\FinancialLedgerEntry;
use Illuminate\Support\Carbon;

class LedgerReconciliationService
{
    public function buildMonthlyTrialBalance(int $tenantId, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $entries = FinancialLedgerEntry::query()
            ->with('financialAccount')
            ->where('tenant_id', $tenantId)
            ->whereBetween('occurred_at', [$start, $end])
            ->get();

        $accountSummaries = [];
        $totalDebits = 0.0;
        $totalCredits = 0.0;

        foreach ($entries->groupBy('financial_account_id') as $accountId => $accountEntries) {
            $financialAccount = $accountEntries->first()->financialAccount;

            $debits = $accountEntries->where('entry_type', 'debit')->sum('amount');
            $credits = $accountEntries->where('entry_type', 'credit')->sum('amount');

            $totalDebits += $debits;
            $totalCredits += $credits;

            $accountSummaries[] = [
                'account_id' => $accountId,
                'code' => $financialAccount?->code,
                'name' => $financialAccount?->name ?? $accountEntries->first()->account,
                'type' => $financialAccount?->type,
                'debits' => number_format($debits, 2, '.', ''),
                'credits' => number_format($credits, 2, '.', ''),
                'balance' => number_format($debits - $credits, 2, '.', ''),
            ];
        }

        usort($accountSummaries, fn ($a, $b) => strcmp((string) $a['code'], (string) $b['code']));

        return [
            'tenant_id' => $tenantId,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'accounts' => $accountSummaries,
            'totals' => [
                'debits' => number_format($totalDebits, 2, '.', ''),
                'credits' => number_format($totalCredits, 2, '.', ''),
                'balanced' => round($totalDebits - $totalCredits, 2) === 0.0,
            ],
        ];
    }
}
