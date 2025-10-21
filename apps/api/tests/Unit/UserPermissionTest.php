<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_requires_explicit_wildcard_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertFalse($user->hasPermission('*'));

        $permission = Permission::query()->create([
            'slug' => '*',
            'name' => 'Wildcard',
            'module' => 'system',
        ]);

        $user->permissions()->attach($permission->id);
        $user->refresh();

        $this->assertTrue($user->hasPermission('*'));
    }
}
