<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\Finance\ChartOfAccountsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExpenseLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reimbursing_expense_posts_balanced_ledger_entries(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $submitter = User::factory()->create(['tenant_id' => $tenant->id]);
        $approver = User::factory()->create(['tenant_id' => $tenant->id]);
        $reimburser = User::factory()->create(['tenant_id' => $tenant->id]);

        app(ChartOfAccountsService::class)->ensureDefaultsForTenant($tenant);

        /** @var ExpenseService $service */
        $service = app(ExpenseService::class);

        $expense = $service->createExpense([
            'tenant_id' => $tenant->id,
            'submitted_by' => $submitter->id,
            'title' => 'Youth outing supplies',
            'description' => 'Snacks and drinks',
            'currency' => 'USD',
            'line_items' => [
                ['description' => 'Snacks', 'amount' => 120],
                ['description' => 'Drinks', 'amount' => 30],
            ],
        ], $submitter);

        $expense = $service->submitExpense($expense, $submitter);
        $expense = $service->approveExpense($expense, $approver);
        $expense = $service->reimburseExpense($expense, $reimburser);

        $entries = $expense->ledgerEntries()->orderBy('id')->get();

        $this->assertCount(2, $entries);
        $this->assertSame($expense->id, $entries->first()->expense_id);
        $this->assertSame('expense.ministry.general', $entries->first()->financialAccount?->code);
        $this->assertSame('assets.cash.checking', $entries->last()->financialAccount?->code);
        $this->assertEquals(150.00, (float) $entries->first()->amount);
        $this->assertEquals(150.00, (float) $entries->last()->amount);
        $this->assertSame(
            ['expense', 'expense'],
            $entries->pluck('metadata.kind')->all()
        );
    }
}
