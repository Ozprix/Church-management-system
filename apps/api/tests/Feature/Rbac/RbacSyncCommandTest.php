<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Services\Rbac\RbacManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RbacSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_command_bootstraps_roles_and_features_for_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        // Ensure the tenant is missing seeded roles/features before running the command.
        Role::query()->where('tenant_id', $tenant->id)->delete();
        TenantFeature::query()->where('tenant_id', $tenant->id)->delete();

        $exitCode = Artisan::call('rbac:sync', [
            '--tenant' => [$tenant->slug],
        ]);

        $this->assertSame(0, $exitCode);

        $this->assertTrue(Role::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'tenant_owner')
            ->exists());

        $this->assertTrue(TenantFeature::query()
            ->where('tenant_id', $tenant->id)
            ->where('feature', 'rbac')
            ->exists());
    }

    public function test_sync_command_prunes_legacy_roles_and_features_when_requested(): void
    {
        $tenant = Tenant::factory()->create();

        /** @var RbacManager $manager */
        $manager = app(RbacManager::class);
        $manager->bootstrapTenant($tenant);

        Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Legacy Role',
            'slug' => 'legacy-role',
        ]);

        TenantFeature::query()->create([
            'tenant_id' => $tenant->id,
            'feature' => 'legacy-feature',
            'is_enabled' => true,
        ]);

        $exitCode = Artisan::call('rbac:sync', [
            '--tenant' => [$tenant->slug],
            '--prune-roles' => true,
            '--prune-features' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $this->assertFalse(Role::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'legacy-role')
            ->exists());

        $this->assertFalse(TenantFeature::query()
            ->where('tenant_id', $tenant->id)
            ->where('feature', 'legacy-feature')
            ->exists());
    }
}
