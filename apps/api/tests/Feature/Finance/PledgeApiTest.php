<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Fund;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PledgeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_pledge(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'member_id' => $member->id,
            'fund_id' => $fund->id,
            'amount' => 500,
            'currency' => 'USD',
            'frequency' => 'monthly',
            'start_date' => Carbon::parse('2024-02-01')->toDateString(),
            'status' => 'active',
            'notes' => 'Building campaign pledge.',
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/pledges', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.amount', '500.00')
            ->assertJsonPath('data.member.id', $member->id)
            ->assertJsonPath('data.fund.id', $fund->id);

        $this->assertDatabaseHas('pledges', [
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'fund_id' => $fund->id,
            'amount' => 500.00,
            'status' => 'active',
        ]);
    }

    public function test_it_updates_pledge(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);
        $newFund = Fund::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        $pledge = Pledge::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'fund_id' => $fund->id,
            'amount' => 200.00,
            'status' => 'active',
        ]);

        $payload = [
            'fund_id' => $newFund->id,
            'amount' => 300,
            'status' => 'fulfilled',
            'fulfilled_amount' => 300,
            'notes' => 'Completed ahead of schedule.',
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->putJson("/api/v1/pledges/{$pledge->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.amount', '300.00')
            ->assertJsonPath('data.status', 'fulfilled')
            ->assertJsonPath('data.fund.id', $newFund->id);

        $this->assertDatabaseHas('pledges', [
            'id' => $pledge->id,
            'fund_id' => $newFund->id,
            'amount' => 300.00,
            'fulfilled_amount' => 300.00,
            'status' => 'fulfilled',
        ]);
    }

    public function test_pledge_index_filters_by_status(): void
    {
        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $fund = Fund::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantAdmin($tenant);

        Pledge::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'fund_id' => $fund->id,
            'status' => 'active',
        ]);

        Pledge::factory()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'fund_id' => $fund->id,
            'status' => 'fulfilled',
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/pledges?status=fulfilled');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('fulfilled', $response->json('data.0.status'));
    }
}
