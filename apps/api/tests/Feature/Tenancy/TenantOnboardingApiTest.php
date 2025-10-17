<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Tenant;
use App\Services\TenantOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantOnboardingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_tenant_and_assigns_plan(): void
    {
        $admin = $this->actingAsTenantAdmin();
        $plan = Plan::factory()->create();

        $payload = [
            'name' => 'Grace Chapel',
            'slug' => 'grace-chapel',
            'plan_code' => $plan->code,
            'plan_options' => [
                'status' => 'trial',
                'seat_limit' => 25,
            ],
            'domains' => [
                ['hostname' => 'grace.example.com', 'is_primary' => true],
            ],
            'admin' => [
                'name' => 'Grace Owner',
                'email' => 'owner@grace.test',
                'password' => 'StrongPass123',
            ],
        ];

        $headerTenant = (string) (optional($admin->tenant)->uuid ?? $admin->tenant_id);

        $response = $this
            ->withHeader('X-Tenant-ID', $headerTenant)
            ->postJson('/api/v1/tenants', $payload);

        $response->assertCreated()->assertJsonPath('data.name', 'Grace Chapel');

        $tenant = Tenant::query()->where('slug', 'grace-chapel')->first();
        $this->assertNotNull($tenant);
        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'hostname' => 'grace.example.com',
        ]);

        $this->assertDatabaseHas('tenant_plans', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'trial',
        ]);

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'email' => 'owner@grace.test',
        ]);
    }

    public function test_it_verifies_domain(): void
    {
        $tenant = Tenant::factory()->create();
        $domain = $tenant->domains()->create([
            'hostname' => 'verify.example.com',
            'is_primary' => true,
            'verification_token' => 'token-123',
        ]);

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson("/api/v1/tenants/{$tenant->id}/domains/{$domain->id}/verify", [
                'token' => 'token-123',
            ]);

        $response->assertOk()->assertJsonPath('data.domain.id', $domain->id);
        $this->assertDatabaseHas('tenant_domains', [
            'id' => $domain->id,
            'verification_token' => null,
        ]);
    }

    public function test_it_lists_active_plans(): void
    {
        $plan = Plan::factory()->create([
            'code' => 'growth',
            'name' => 'Growth Plan',
        ]);

        PlanFeature::factory()->create([
            'plan_id' => $plan->id,
            'feature' => 'members',
            'limit' => 500,
        ]);

        $response = $this->getJson('/api/v1/plans');

        $response->assertOk()->assertJsonPath('data.0.code', 'growth');
    }

    public function test_tenant_profile_returns_subscription_and_usage(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser($tenant, roles: ['tenant_owner']);
        $plan = Plan::factory()->create(['code' => 'standard']);
        PlanFeature::factory()->create([
            'plan_id' => $plan->id,
            'feature' => 'members',
            'limit' => 1000,
        ]);

        /** @var TenantOnboardingService $service */
        $service = app(TenantOnboardingService::class);
        $service->assignPlan($tenant, $plan, ['status' => 'active']);
        $service->incrementUsage($tenant, 'members', 25);

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson('/api/v1/tenant/profile');

        $response->assertOk()
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.plan.code', 'standard')
            ->assertJsonPath('data.usage.0.used', 25);
    }

    public function test_tenant_can_manage_domains(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser($tenant, roles: ['tenant_owner']);

        $create = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/tenant/domains', [
                'hostname' => 'new.example.com',
            ]);

        $create->assertCreated();
        $domainId = $create->json('data.id');

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson("/api/v1/tenant/domains/{$domainId}/regenerate")
            ->assertOk();

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->deleteJson("/api/v1/tenant/domains/{$domainId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tenant_domains', [
            'id' => $domainId,
        ]);
    }
}
