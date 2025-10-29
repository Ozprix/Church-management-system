<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Jobs\SendNotificationJob;
use App\Models\Expense;
use App\Models\ExpenseLineItem;
use App\Models\Tenant;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_expense_with_line_items(): void
    {
        $tenant = Tenant::factory()->create();
        $submitter = $this->actingAsTenantAdmin($tenant);

        $payload = [
            'title' => 'Student ministry supplies',
            'description' => 'Snacks and supplies for weekly student gathering.',
            'currency' => 'USD',
            'incurred_at' => Carbon::parse('2024-03-12')->toDateString(),
            'line_items' => [
                ['description' => 'Snacks', 'amount' => 45.50, 'category' => 'hospitality'],
                ['description' => 'Craft supplies', 'amount' => 32.10],
            ],
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/expenses', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Student ministry supplies')
            ->assertJsonPath('data.total_amount', '77.60')
            ->assertJsonCount(2, 'data.line_items')
            ->assertJsonPath('data.submitter.id', $submitter->id);

        $this->assertDatabaseHas('expenses', [
            'tenant_id' => $tenant->id,
            'title' => 'Student ministry supplies',
            'total_amount' => 77.60,
            'currency' => 'USD',
            'status' => Expense::STATUS_DRAFT,
        ]);
    }

    public function test_it_submits_expense(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->actingAsTenantAdmin($tenant);

        /** @var Expense $expense */
        $expense = Expense::factory()->create([
            'tenant_id' => $tenant->id,
            'submitted_by' => $admin->id,
            'status' => Expense::STATUS_DRAFT,
        ]);

        ExpenseLineItem::factory()->create([
            'expense_id' => $expense->id,
            'description' => 'Mileage reimbursement',
            'amount' => 25.00,
        ]);
        $expense->forceFill(['total_amount' => 25.00])->save();

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson("/api/v1/expenses/{$expense->id}/submit");

        $response->assertOk()
            ->assertJsonPath('data.status', Expense::STATUS_PENDING);

        $this->assertNotNull($response->json('data.submitted_at'));

        $expense->refresh();
        $this->assertSame(Expense::STATUS_PENDING, $expense->status);
        $this->assertNotNull($expense->submitted_at);
    }

    public function test_it_approves_and_reimburses_expense(): void
    {
        $tenant = Tenant::factory()->create();
        $approver = $this->actingAsTenantAdmin($tenant);

        /** @var Expense $expense */
        $expense = Expense::factory()->pending()->create([
            'tenant_id' => $tenant->id,
            'submitted_by' => $approver->id,
        ]);

        ExpenseLineItem::factory()->create([
            'expense_id' => $expense->id,
            'description' => 'Conference ticket',
            'amount' => 150.00,
        ]);
        $expense->forceFill(['total_amount' => 150.00])->save();

        Queue::fake();

        $approveResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson("/api/v1/expenses/{$expense->id}/approve");

        $approveResponse->assertOk()
            ->assertJsonPath('data.status', Expense::STATUS_APPROVED);

        $expense->refresh();
        $this->assertSame(Expense::STATUS_APPROVED, $expense->status);

        $reimburseResponse = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson("/api/v1/expenses/{$expense->id}/reimburse");

        $reimburseResponse->assertOk()
            ->assertJsonPath('data.status', Expense::STATUS_REIMBURSED);

        $expense->refresh();
        $this->assertSame(Expense::STATUS_REIMBURSED, $expense->status);

        Queue::assertPushed(SendNotificationJob::class, 2);
    }

    public function test_it_lists_expenses_by_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        Expense::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'status' => Expense::STATUS_PENDING,
            'total_amount' => 40,
        ]);

        Expense::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => Expense::STATUS_REIMBURSED,
            'total_amount' => 80,
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/expenses?status=pending');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(Expense::STATUS_PENDING, $response->json('data.0.status'));
    }

    public function test_it_validates_line_items(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'title' => 'Invalid expense',
            'line_items' => [
                ['description' => '', 'amount' => 0],
            ],
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/expenses', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['line_items.0.description', 'line_items.0.amount']);
    }
}
