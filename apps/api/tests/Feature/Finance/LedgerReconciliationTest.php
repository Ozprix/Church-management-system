<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\FinancialLedgerEntry;
use App\Models\Fund;
use App\Models\Member;
use App\Models\Tenant;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LedgerReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_balance_endpoint_returns_balanced_totals(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-15 10:00:00', 'UTC'));

        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);

        /** @var FinanceService $financeService */
        $financeService = app(FinanceService::class);

        $financeService->recordDonation([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'amount' => 125,
            'currency' => 'USD',
            'status' => 'succeeded',
            'received_at' => Carbon::parse('2024-01-12 09:00:00', 'UTC')->toIso8601String(),
            'items' => [
                ['fund_id' => $fund->id, 'amount' => 125],
            ],
        ]);

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/finance/ledger/trial-balance?month=2024-01');

        $response->assertOk();
        $response->assertJsonPath('totals.balanced', true);
        $response->assertJsonPath('totals.debits', '125.00');
        $response->assertJsonPath('totals.credits', '125.00');

        Carbon::setTestNow();
    }

    public function test_ledger_check_command_reports_success_when_balanced(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);

        /** @var FinanceService $financeService */
        $financeService = app(FinanceService::class);

        $financeService->recordDonation([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'amount' => 80,
            'currency' => 'USD',
            'status' => 'succeeded',
            'received_at' => Carbon::parse('2024-02-05 08:00:00', 'UTC')->toIso8601String(),
            'items' => [
                ['fund_id' => $fund->id, 'amount' => 80],
            ],
        ]);

        $exitCode = Artisan::call('finance:ledger-check', [
            '--tenant' => $tenant->id,
            '--month' => '2024-02',
        ]);

        $this->assertSame(0, $exitCode);
    }

    public function test_ledger_check_command_reports_failure_when_unbalanced(): void
    {
        $tenant = Tenant::factory()->create();

        $account = $tenant->financialAccounts()->where('code', 'income.donations')->firstOrFail();

        FinancialLedgerEntry::create([
            'tenant_id' => $tenant->id,
            'donation_id' => null,
            'pledge_id' => null,
            'financial_account_id' => $account->id,
            'entry_type' => 'debit',
            'account' => $account->name,
            'amount' => 50,
            'currency' => 'USD',
            'occurred_at' => Carbon::parse('2024-03-10 12:00:00', 'UTC'),
            'description' => 'Test imbalance',
            'metadata' => ['kind' => 'manual-test'],
        ]);

        $exitCode = Artisan::call('finance:ledger-check', [
            '--tenant' => $tenant->id,
            '--month' => '2024-03',
        ]);

        $this->assertSame(1, $exitCode);
    }
}
