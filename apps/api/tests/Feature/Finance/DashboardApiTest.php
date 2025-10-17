<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Fund;
use App\Models\Member;
use App\Models\Tenant;
use App\Services\FinanceService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_finance_dashboard_summary(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantManager = app(TenantManager::class);
        $tenantManager->setTenant($tenant);

        /** @var FinanceService $financeService */
        $financeService = app(FinanceService::class);

        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $fundA = Fund::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Building Fund']);
        $fundB = Fund::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Missions']);

        $financeService->recordDonation([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'amount' => 200,
            'currency' => 'USD',
            'status' => 'succeeded',
            'received_at' => Carbon::now()->subDays(2)->toIso8601String(),
            'items' => [
                ['fund_id' => $fundA->id, 'amount' => 200],
            ],
        ]);

        $financeService->recordDonation([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'amount' => 80,
            'currency' => 'USD',
            'status' => 'succeeded',
            'received_at' => Carbon::now()->subDay()->toIso8601String(),
            'items' => [
                ['fund_id' => $fundB->id, 'amount' => 80],
            ],
        ]);

        $financeService->recordDonation([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'amount' => 50,
            'currency' => 'USD',
            'status' => 'pending',
            'items' => [
                ['fund_id' => $fundA->id, 'amount' => 50],
            ],
        ]);

        $financeService->createPledge([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'fund_id' => $fundA->id,
            'amount' => 500,
            'fulfilled_amount' => 150,
            'currency' => 'USD',
            'frequency' => 'monthly',
            'status' => 'active',
        ]);

        $financeService->createPledge([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'fund_id' => $fundB->id,
            'amount' => 300,
            'fulfilled_amount' => 300,
            'currency' => 'USD',
            'frequency' => 'monthly',
            'status' => 'fulfilled',
        ]);

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/finance/dashboard');

        $response->assertOk()
            ->assertJsonPath('totals.donations', 280)
            ->assertJsonPath('totals.active_pledges', 500)
            ->assertJsonPath('totals.fulfilled_pledges', 300)
            ->assertJsonStructure([
                'totals' => ['donations', 'month_to_date', 'average_donation', 'active_pledges', 'fulfilled_pledges'],
                'recurring_pledges',
                'top_funds',
                'recent_donations',
            ]);

        $this->assertNotEmpty($response->json('top_funds'));
        $this->assertSame('Building Fund', $response->json('top_funds.0.fund_name'));

        $tenantManager->forgetTenant();
    }
}
