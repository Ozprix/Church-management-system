<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_endpoint_returns_roles_with_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser(
            tenant: $tenant,
            roles: ['tenant_owner'],
            grantSuperPermission: false
        );

        $response = $this->getJson('/api/v1/rbac/roles');

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
        $this->actingAsTenantUser(
            tenant: $tenant,
            roles: ['member_manager'],
            grantSuperPermission: false
        );

        $this->getJson('/api/v1/rbac/permissions')->assertForbidden();
    }

    public function test_permissions_endpoint_returns_permission_registry(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser(
            tenant: $tenant,
            roles: ['tenant_owner'],
            grantSuperPermission: false
        );

        $response = $this->getJson('/api/v1/rbac/permissions');

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
