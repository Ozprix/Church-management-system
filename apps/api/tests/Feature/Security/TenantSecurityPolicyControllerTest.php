<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Rbac\RbacManager;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantSecurityPolicyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_manage_policy_and_view_compliance_summary(): void
    {
        $tenant = Tenant::factory()->create();

        /** @var RbacManager $rbac */
        $rbac = app(RbacManager::class);
        $rbac->bootstrapTenant($tenant);

        $manager = $this->actingAsTenantUser(
            tenant: $tenant,
            permissions: ['users.manage_security'],
            roles: ['tenant_owner'],
            grantSuperPermission: false
        );

        /** @var TwoFactorAuthenticationService $twoFactor */
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $managerSecret = $twoFactor->generateSecret($manager);
        $manager->forceFill([
            'two_factor_secret' => $twoFactor->encryptSecret($managerSecret),
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($twoFactor->generateRecoveryCodes()),
            'two_factor_confirmed_at' => now(),
        ])->save();
        Sanctum::actingAs($manager->fresh(), ['*']);

        $compliantUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Compliant User',
        ]);
        $rbac->assignRole($compliantUser, 'tenant_owner');
        $compliantSecret = $twoFactor->generateSecret($compliantUser);
        $compliantUser->forceFill([
            'two_factor_secret' => $twoFactor->encryptSecret($compliantSecret),
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($twoFactor->generateRecoveryCodes()),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $nonCompliantUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Non-Compliant User',
        ]);
        $rbac->assignRole($nonCompliantUser, 'tenant_owner');

        $otherRoleUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Member Manager',
        ]);
        $rbac->assignRole($otherRoleUser, 'member_manager');

        $defaultRoles = config('security.two_factor.default_enforced_roles');

        $showResponse = $this->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson('/api/v1/security/two-factor/policy');

        $showResponse->assertOk()
            ->assertJsonPath('policy.enforce_two_factor', false)
            ->assertJsonPath('policy.enforced_role_slugs', $defaultRoles);

        $updateResponse = $this->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->putJson('/api/v1/security/two-factor/policy', [
                'enforce_two_factor' => true,
                'enforced_role_slugs' => ['tenant_owner'],
                'enforced_permission_slugs' => ['users.manage_security'],
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('policy.enforce_two_factor', true)
            ->assertJsonPath('policy.enforced_role_slugs', ['tenant_owner'])
            ->assertJsonPath('policy.enforced_permission_slugs', ['users.manage_security']);

        $compliance = $this->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson('/api/v1/security/two-factor/compliance');

        $compliance->assertOk()
            ->assertJsonPath('policy.enforce_two_factor', true)
            ->assertJsonPath('summary.total_users', 4)
            ->assertJsonPath('summary.enforced_user_count', 3) // manager + compliant + non-compliant
            ->assertJsonPath('summary.non_compliant_user_count', 1)
            ->assertJsonPath('summary.compliant_user_count', 2);

        $nonCompliantList = $compliance->json('non_compliant_users');
        $this->assertCount(1, $nonCompliantList);
        $this->assertSame('Non-Compliant User', Arr::get($nonCompliantList, '0.name'));
        $this->assertFalse(Arr::get($nonCompliantList, '0.two_factor_enabled'));
        $this->assertTrue(Arr::get($nonCompliantList, '0.requires_two_factor'));
    }

    public function test_update_policy_validates_roles(): void
    {
        $tenant = Tenant::factory()->create();

        /** @var RbacManager $rbac */
        $rbac = app(RbacManager::class);
        $rbac->bootstrapTenant($tenant);

        $manager = $this->actingAsTenantUser(
            tenant: $tenant,
            permissions: ['users.manage_security'],
            roles: ['tenant_owner'],
            grantSuperPermission: false
        );

        /** @var TwoFactorAuthenticationService $twoFactor */
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactor->generateSecret($manager);
        $manager->forceFill([
            'two_factor_secret' => $twoFactor->encryptSecret($secret),
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($twoFactor->generateRecoveryCodes()),
            'two_factor_confirmed_at' => now(),
        ])->save();
        Sanctum::actingAs($manager->fresh(), ['*']);

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->putJson('/api/v1/security/two-factor/policy', [
                'enforce_two_factor' => true,
                'enforced_role_slugs' => ['does-not-exist'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['enforced_role_slugs.0']);
    }

    public function test_user_without_permission_cannot_access_policy_routes(): void
    {
        $tenant = Tenant::factory()->create();

        /** @var RbacManager $rbac */
        $rbac = app(RbacManager::class);
        $rbac->bootstrapTenant($tenant);

        $user = $this->actingAsTenantUser(
            tenant: $tenant,
            permissions: [],
            roles: ['member_manager'],
            grantSuperPermission: false
        );

        /** @var TwoFactorAuthenticationService $twoFactor */
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactor->generateSecret($user);
        $user->forceFill([
            'two_factor_secret' => $twoFactor->encryptSecret($secret),
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($twoFactor->generateRecoveryCodes()),
            'two_factor_confirmed_at' => now(),
        ])->save();
        Sanctum::actingAs($user->fresh(), ['*']);

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson('/api/v1/security/two-factor/policy');

        $response->assertStatus(403);
    }
}
