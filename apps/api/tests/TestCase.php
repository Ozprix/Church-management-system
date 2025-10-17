<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Rbac\RbacManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        app(RbacManager::class)->syncRegistry();
    }

    protected function actingAsTenantAdmin(?Tenant $tenant = null): User
    {
        return $this->actingAsTenantUser(tenant: $tenant, roles: ['admin']);
    }

    protected function actingAsTenantUser(
        ?Tenant $tenant = null,
        array $permissions = [],
        array $roles = [],
        bool $grantSuperPermission = true
    ): User
    {
        /** @var RbacManager $rbac */
        $rbac = app(RbacManager::class);

        $tenant ??= Tenant::factory()->create();

        $rbac->bootstrapTenant($tenant);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        if (! empty($roles)) {
            foreach ($roles as $role) {
                $rbac->assignRole($user, $role);
            }
        }

        if (! empty($permissions)) {
            $rbac->grantPermissions($user, $permissions);
        }

        if (empty($roles) && empty($permissions)) {
            $rbac->assignRole($user, 'admin');
        }

        $superPermission = config('permissions.super_permission');
        if ($grantSuperPermission && $superPermission) {
            $rbac->grantPermissions($user, [$superPermission]);
        }

        $abilities = $grantSuperPermission
            ? ['*']
            : $user->allPermissionSlugs()->values()->all();

        if (empty($abilities)) {
            $abilities = ['default'];
        }

        Sanctum::actingAs($user->fresh(), $abilities);

        return $user;
    }
}
