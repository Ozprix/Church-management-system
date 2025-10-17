<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Models\Role;
use App\Models\Tenant;
use App\Services\Rbac\RbacManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_manage_security_permission_is_seeded_and_assigned(): void
    {
        /** @var RbacManager $manager */
        $manager = app(RbacManager::class);
        $manager->syncRegistry();

        $tenant = Tenant::factory()->create();
        $manager->bootstrapTenant($tenant);

        $this->assertDatabaseHas('permissions', [
            'slug' => 'users.manage_security',
        ]);

        /** @var Role $tenantOwner */
        $tenantOwner = Role::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'tenant_owner')
            ->with('permissions')
            ->firstOrFail();

        $this->assertTrue(
            $tenantOwner->permissions->pluck('slug')->contains('users.manage_security'),
            'Tenant owner role should include users.manage_security permission.'
        );
    }
}
