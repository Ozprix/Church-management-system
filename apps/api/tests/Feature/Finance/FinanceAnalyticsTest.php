<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Donation;
use App\Models\DonationItem;
use App\Models\Fund;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_analytics_returns_metrics(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);

        $donation = Donation::factory()
            ->for($tenant, 'tenant')
            ->for($member, 'member')
            ->create(['status' => 'succeeded', 'amount' => 150, 'received_at' => now()->subDays(3)]);

        DonationItem::factory()->create([
            'donation_id' => $donation->id,
            'fund_id' => $fund->id,
            'amount' => 150,
        ]);

        Pledge::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'fund_id' => $fund->id,
            'status' => 'active',
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/finance/analytics');

        $response->assertOk();
        $response->assertJsonStructure([
            'totals' => [
                'donations_amount',
                'donations_this_month',
                'average_donation',
                'active_pledges',
            ],
            'by_status',
            'by_fund',
            'donations_trend',
            'top_donors',
            'recent_donations',
        ]);
    }

    public function test_finance_analytics_export_streams_csv(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantAdmin($tenant);

        $donation = Donation::factory()->for($tenant, 'tenant')->create();
        DonationItem::factory()->create([
            'donation_id' => $donation->id,
            'fund_id' => Fund::factory()->create(['tenant_id' => $tenant->id])->id,
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->get('/api/v1/finance/analytics/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
