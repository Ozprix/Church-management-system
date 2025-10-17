<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\VolunteerRole;
use App\Services\TenantOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_creation_respects_plan_limit(): void
    {
        $plan = Plan::factory()->create(['code' => 'starter']);
        PlanFeature::factory()->create([
            'plan_id' => $plan->id,
            'feature' => 'members',
            'limit' => 1,
        ]);

        $tenant = Tenant::factory()->create();

        /** @var TenantOnboardingService $onboarding */
        $onboarding = app(TenantOnboardingService::class);
        $onboarding->assignPlan($tenant, $plan, ['status' => 'active']);

        $this->actingAsTenantUser($tenant, roles: ['admin']);

        $payload = [
            'first_name' => 'Grace',
            'last_name' => 'Member',
            'membership_status' => 'active',
        ];

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/members', $payload)
            ->assertCreated();

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/members', $payload)
            ->assertStatus(402);
    }

    public function test_volunteer_signup_respects_plan_limit(): void
    {
        $plan = Plan::factory()->create(['code' => 'serve']);
        PlanFeature::factory()->create([
            'plan_id' => $plan->id,
            'feature' => 'volunteer_signups',
            'limit' => 1,
        ]);

        $tenant = Tenant::factory()->create();

        /** @var TenantOnboardingService $onboarding */
        $onboarding = app(TenantOnboardingService::class);
        $onboarding->assignPlan($tenant, $plan, ['status' => 'active']);

        $this->actingAsTenantUser($tenant, roles: ['admin']);

        $payload = [
            'tenant_id' => $tenant->id,
            'name' => 'John Volunteer',
        ];

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/volunteer-signups', $payload)
            ->assertCreated();

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/volunteer-signups', $payload)
            ->assertStatus(402);
    }

    public function test_confirmed_signup_releases_plan_usage(): void
    {
        $plan = Plan::factory()->create(['code' => 'serve-pro']);
        PlanFeature::factory()->create([
            'plan_id' => $plan->id,
            'feature' => 'volunteer_signups',
            'limit' => 1,
        ]);

        $tenant = Tenant::factory()->create();

        /** @var TenantOnboardingService $onboarding */
        $onboarding = app(TenantOnboardingService::class);
        $onboarding->assignPlan($tenant, $plan, ['status' => 'active']);

        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        $role = VolunteerRole::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsTenantUser($tenant, roles: ['admin']);

        $signup = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/volunteer-signups', [
                'tenant_id' => $tenant->id,
                'volunteer_role_id' => $role->id,
                'member_id' => $member->id,
                'name' => 'Test Volunteer',
            ])
            ->assertCreated()
            ->json('data');

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->patchJson("/api/v1/volunteer-signups/{$signup['id']}", [
                'status' => 'confirmed',
                'assignment' => [
                    'member_id' => $member->id,
                    'starts_at' => now()->addDay()->toIso8601String(),
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned');

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/volunteer-signups', [
                'tenant_id' => $tenant->id,
                'volunteer_role_id' => $role->id,
                'member_id' => $member->id,
                'name' => 'Second Volunteer',
            ])
            ->assertCreated();
    }

    public function test_domain_limit_enforced_and_released(): void
    {
        $plan = Plan::factory()->create(['code' => 'multi-site']);
        PlanFeature::factory()->create([
            'plan_id' => $plan->id,
            'feature' => 'domains',
            'limit' => 1,
        ]);

        $tenant = Tenant::factory()->create();

        /** @var TenantOnboardingService $onboarding */
        $onboarding = app(TenantOnboardingService::class);
        $onboarding->assignPlan($tenant, $plan, ['status' => 'active']);

        $this->actingAsTenantUser($tenant, roles: ['tenant_owner']);

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/tenant/domains', [
                'hostname' => 'alpha.example.com',
            ]);

        $response->assertCreated();
        $domainId = $response->json('data.id');

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/tenant/domains', [
                'hostname' => 'beta.example.com',
            ])
            ->assertStatus(402);

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->deleteJson("/api/v1/tenant/domains/{$domainId}")
            ->assertNoContent();

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->postJson('/api/v1/tenant/domains', [
                'hostname' => 'gamma.example.com',
            ])
            ->assertCreated();
    }
}
