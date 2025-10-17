<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Fund;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FundApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_fund(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'name' => 'Missions Fund',
            'slug' => 'missions-fund',
            'description' => 'Supports evangelism and outreach initiatives.',
            'is_active' => true,
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->postJson('/api/v1/funds', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Missions Fund')
            ->assertJsonPath('data.slug', 'missions-fund');

        $this->assertDatabaseHas('funds', [
            'tenant_id' => $tenant->id,
            'name' => 'Missions Fund',
            'slug' => 'missions-fund',
        ]);
    }

    public function test_fund_index_is_scoped_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        Fund::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        Fund::factory()->count(3)->create();

        $this->actingAsTenantAdmin($tenant);

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->getJson('/api/v1/funds');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_it_updates_fund(): void
    {
        $tenant = Tenant::factory()->create();
        $fund = Fund::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'General Fund',
            'slug' => 'general-fund',
            'is_active' => true,
        ]);

        $this->actingAsTenantAdmin($tenant);

        $payload = [
            'name' => 'General Operations',
            'description' => 'Covers utilities and administrative costs.',
            'is_active' => false,
        ];

        $response = $this
            ->withHeader('X-Tenant-ID', $tenant->uuid)
            ->putJson("/api/v1/funds/{$fund->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.name', 'General Operations')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('funds', [
            'id' => $fund->id,
            'tenant_id' => $tenant->id,
            'name' => 'General Operations',
            'description' => 'Covers utilities and administrative costs.',
            'is_active' => false,
        ]);
    }
}
