<?php

namespace App\Services\Rbac;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RbacManager
{
    public function syncRegistry(): void
    {
        $modules = config('permissions.modules', []);

        foreach ($modules as $moduleKey => $module) {
            $permissions = Arr::get($module, 'permissions', []);

            foreach ($permissions as $slug => $meta) {
                Permission::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => Arr::get($meta, 'name', $slug),
                        'description' => Arr::get($meta, 'description'),
                        'module' => $moduleKey,
                    ]
                );
            }
        }

        $superPermission = config('permissions.super_permission', null);
        if ($superPermission) {
            Permission::query()->updateOrCreate(
                ['slug' => $superPermission],
                [
                    'name' => 'Super administrator access',
                    'description' => 'Grants every permission for the tenant.',
                    'module' => 'system',
                ]
            );
        }
    }

    public function bootstrapTenant(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant): void {
            $this->syncRegistry();

            $modules = config('permissions.modules', []);

            foreach ($modules as $moduleKey => $module) {
                TenantFeature::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'feature' => $moduleKey],
                    [
                        'is_enabled' => (bool) Arr::get($module, 'enabled_by_default', true),
                        'metadata' => [
                            'label' => Arr::get($module, 'label'),
                            'description' => Arr::get($module, 'description'),
                        ],
                    ]
                );
            }

            $rolesConfig = config('permissions.roles', []);
            $permissions = Permission::query()->pluck('id', 'slug');

            foreach ($rolesConfig as $slug => $roleConfig) {
                /** @var Role $role */
                $role = Role::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'slug' => $slug,
                    ],
                    [
                        'name' => Arr::get($roleConfig, 'name', $slug),
                        'description' => Arr::get($roleConfig, 'description'),
                        'is_default' => (bool) Arr::get($roleConfig, 'is_default', false),
                    ]
                );

                $grants = Collection::make(Arr::get($roleConfig, 'grants', []));

                if ($grants->contains('*')) {
                    $role->permissions()->sync($permissions->values());
                    continue;
                }

                $permissionIds = $permissions->only($grants->all())->values();
                $role->permissions()->sync($permissionIds);
            }
        });
    }

    public function assignRole(User $user, string $roleSlug, ?User $assignedBy = null): Role
    {
        /** @var Role $role */
        $role = Role::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('slug', $roleSlug)
            ->firstOrFail();

        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'assigned_at' => now(),
                'assigned_by' => $assignedBy?->id,
            ],
        ]);

        return $role;
    }

    public function grantPermissions(User $user, array $permissionSlugs): void
    {
        $permissionIds = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->pluck('id')
            ->all();

        if (empty($permissionIds)) {
            return;
        }

        $user->permissions()->syncWithoutDetaching($permissionIds);
    }

    public function featureEnabledForTenant(int $tenantId, string $feature): bool
    {
        $featureRecord = TenantFeature::query()
            ->where('tenant_id', $tenantId)
            ->where('feature', $feature)
            ->first();

        if ($featureRecord) {
            return $featureRecord->is_enabled;
        }

        $module = Arr::get(config('permissions.modules'), $feature);

        return (bool) Arr::get($module, 'enabled_by_default', true);
    }
}
