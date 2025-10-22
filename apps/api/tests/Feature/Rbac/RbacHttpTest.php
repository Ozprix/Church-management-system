<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Rbac\RbacManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RbacHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_endpoint_returns_roles_with_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser(
            tenant: $tenant,
            roles: ['tenant_owner'],
            grantSuperPermission: false
        );

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson('/api/v1/rbac/roles');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'slug',
                    'permissions' => [
                        [
                            'slug',
                            'module' => [
                                'key',
                                'feature',
                                'is_enabled',
                            ],
                        ],
                    ],
                ],
            ],
            'meta' => [
                'features',
            ],
        ]);
    }

    public function test_permissions_endpoint_requires_permission(): void
    {
        $tenant = Tenant::factory()->create();

        /** @var RbacManager $manager */
        $manager = app(RbacManager::class);
        $manager->bootstrapTenant($tenant);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        Sanctum::actingAs($user->fresh(), ['default']);

        $this->assertFalse(
            $user->allPermissionSlugs()->contains('rbac.view'),
            'Unaffiliated user should not have rbac.view permission.'
        );

        $this->assertFalse(
            Gate::forUser($user)->allows('rbac.view'),
            'Gate should deny rbac.view for unaffiliated user.'
        );

        $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson('/api/v1/rbac/permissions')
            ->assertForbidden();
    }

    public function test_permissions_endpoint_returns_permission_registry(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser(
            tenant: $tenant,
            roles: ['tenant_owner'],
            grantSuperPermission: false
        );

        $response = $this
            ->withHeader('X-Tenant-ID', (string) $tenant->uuid)
            ->getJson('/api/v1/rbac/permissions');

        $response->assertOk();

        $payload = $response->json();
        $this->assertNotEmpty($payload['data']);
        $this->assertNotEmpty($payload['meta']['features']);
        $firstPermission = $payload['data'][0];
        $this->assertArrayHasKey('slug', $firstPermission);
        $this->assertArrayHasKey('module', $firstPermission);
        $this->assertArrayHasKey('is_enabled', $firstPermission['module']);
    }
}
