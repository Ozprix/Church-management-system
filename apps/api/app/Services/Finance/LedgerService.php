<?php

namespace App\Services\Finance;

use App\Models\FinancialLedgerEntry;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use RuntimeException;

class LedgerService
{
    public function __construct(
        private readonly ChartOfAccountsService $chartOfAccountsService,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @param array<string, mixed> $context
     */
    public function post(int $tenantId, array $entries, array $context = []): void
    {
        if (empty($entries)) {
            return;
        }

        $resolved = [];
        $totalDebits = 0.0;
        $totalCredits = 0.0;

        foreach ($entries as $entry) {
            $entryType = $entry['entry_type'] ?? null;
            $amount = (float) ($entry['amount'] ?? 0);
            $currency = $entry['currency'] ?? 'USD';
            $accountCode = $entry['account_code'] ?? null;

            if (! in_array($entryType, ['debit', 'credit'], true)) {
                throw new InvalidArgumentException('Ledger entry type must be debit or credit.');
            }

            if ($amount <= 0) {
                throw new InvalidArgumentException('Ledger entry amount must be greater than zero.');
            }

            if (! $accountCode) {
                throw new InvalidArgumentException('Ledger entry requires an account code.');
            }

            if ($entryType === 'debit') {
                $totalDebits += $amount;
            } else {
                $totalCredits += $amount;
            }

            $account = $this->chartOfAccountsService->resolveAccount($tenantId, $accountCode);

            $metadata = Arr::wrap($entry['metadata'] ?? []);
            if (! array_key_exists('kind', $metadata) && isset($entry['kind'])) {
                $metadata['kind'] = $entry['kind'];
            }
            if ($metadata === []) {
                $metadata = null;
            }

            $resolved[] = [
                'tenant_id' => $tenantId,
                'donation_id' => $entry['donation_id'] ?? $context['donation_id'] ?? null,
                'pledge_id' => $entry['pledge_id'] ?? $context['pledge_id'] ?? null,
                'expense_id' => $entry['expense_id'] ?? $context['expense_id'] ?? null,
                'financial_account_id' => $account->id,
                'entry_type' => $entryType,
                'account' => $account->name,
                'amount' => $amount,
                'currency' => $currency,
                'occurred_at' => $entry['occurred_at'] ?? Carbon::now(),
                'description' => $entry['description'] ?? null,
                'metadata' => $metadata,
            ];
        }

        if (round($totalDebits - $totalCredits, 2) !== 0.0) {
            throw new RuntimeException('Ledger entries must balance before posting.');
        }

        foreach ($resolved as $payload) {
            FinancialLedgerEntry::create($payload);
        }
    }
}
