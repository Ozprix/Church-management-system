<?php

namespace App\Services\Finance;

use App\Models\FinancialAccount;
use App\Models\Tenant;
use Illuminate\Support\Collection;

class ChartOfAccountsService
{
    /**
     * @var array<int, array{code: string, name: string, type: string, is_system?: bool}>
     */
    private const DEFAULT_ACCOUNTS = [
        ['code' => 'assets.cash.undeposited', 'name' => 'Cash - Undeposited Funds', 'type' => 'asset', 'is_system' => true],
        ['code' => 'assets.cash.checking', 'name' => 'Cash - Operating Checking', 'type' => 'asset', 'is_system' => true],
        ['code' => 'liabilities.deferred.revenue', 'name' => 'Deferred Revenue', 'type' => 'liability', 'is_system' => true],
        ['code' => 'equity.opening.balance', 'name' => 'Opening Balance Equity', 'type' => 'equity', 'is_system' => true],
        ['code' => 'income.donations', 'name' => 'Donations Income', 'type' => 'income', 'is_system' => true],
        ['code' => 'expense.ministry.general', 'name' => 'General Ministry Expenses', 'type' => 'expense', 'is_system' => true],
    ];

    public function ensureDefaultsForTenant(Tenant $tenant): void
    {
        $existingCodes = $tenant->financialAccounts()
            ->pluck('code')
            ->map(fn (string $code) => strtolower($code))
            ->all();

        foreach (self::DEFAULT_ACCOUNTS as $account) {
            if (in_array(strtolower($account['code']), $existingCodes, true)) {
                continue;
            }

            $tenant->financialAccounts()->create([
                'code' => $account['code'],
                'name' => $account['name'],
                'type' => $account['type'],
                'is_system' => $account['is_system'] ?? false,
                'is_active' => true,
            ]);
        }
    }

    public function ensureDefaultsForAllTenants(): void
    {
        Tenant::query()->with('financialAccounts')->chunkById(50, function ($tenants): void {
            /** @var Tenant $tenant */
            foreach ($tenants as $tenant) {
                $this->ensureDefaultsForTenant($tenant);
            }
        });
    }

    public function resolveAccount(int $tenantId, string $code): FinancialAccount
    {
        return FinancialAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->firstOrFail();
    }

    public function listDefaults(): Collection
    {
        return collect(self::DEFAULT_ACCOUNTS);
    }
}
