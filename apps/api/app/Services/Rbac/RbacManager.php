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
    public function syncRegistry(bool $prune = false): void
    {
        $modules = config('permissions.modules', []);
        $trackedSlugs = [];

        foreach ($modules as $moduleKey => $module) {
            $permissions = Arr::get($module, 'permissions', []);

            foreach ($permissions as $slug => $meta) {
                $record = Permission::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => Arr::get($meta, 'name', $slug),
                        'description' => Arr::get($meta, 'description'),
                        'module' => $moduleKey,
                    ]
                );

                $trackedSlugs[] = $record->slug;
            }
        }

        $superPermission = config('permissions.super_permission', null);
        if ($superPermission) {
            $record = Permission::query()->updateOrCreate(
                ['slug' => $superPermission],
                [
                    'name' => 'Super administrator access',
                    'description' => 'Grants every permission for the tenant.',
                    'module' => 'system',
                ]
            );

            $trackedSlugs[] = $record->slug;
        }

        if ($prune) {
            Permission::query()
                ->whereNotIn('slug', array_unique($trackedSlugs))
                ->delete();
        }
    }

    public function bootstrapTenant(Tenant $tenant): void
    {
        $this->syncTenant($tenant);
    }

    public function syncTenant(Tenant $tenant, bool $pruneRoles = false, bool $pruneFeatures = false): void
    {
        DB::transaction(function () use ($tenant, $pruneRoles, $pruneFeatures): void {
            $this->syncRegistry();

            $modules = collect(config('permissions.modules', []));
            $rolesConfig = config('permissions.roles', []);

            $featureKeys = $modules
                ->map(fn (array $module, string $key) => Arr::get($module, 'feature', $key))
                ->values()
                ->all();

            $modules->each(function (array $moduleConfig, string $moduleKey) use ($tenant): void {
                $featureKey = Arr::get($moduleConfig, 'feature', $moduleKey);

                /** @var TenantFeature $feature */
                $feature = TenantFeature::query()->firstOrNew([
                    'tenant_id' => $tenant->id,
                    'feature' => $featureKey,
                ]);

                if (! $feature->exists && $feature->is_enabled === null) {
                    $feature->is_enabled = (bool) Arr::get($moduleConfig, 'enabled_by_default', true);
                }

                $feature->metadata = [
                    'label' => Arr::get($moduleConfig, 'label'),
                    'description' => Arr::get($moduleConfig, 'description'),
                ];

                $feature->save();
            });

            if ($pruneFeatures) {
                TenantFeature::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereNotIn('feature', $featureKeys)
                    ->delete();
            }

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
                        'metadata' => Arr::get($roleConfig, 'metadata', []),
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

            if ($pruneRoles) {
                Role::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereNotIn('slug', array_keys($rolesConfig))
                    ->delete();
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

    public function featureStatesForTenant(Tenant $tenant): Collection
    {
        $modules = collect(config('permissions.modules', []));

        $featureRecords = TenantFeature::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('feature');

        return $modules->mapWithKeys(function (array $moduleConfig, string $moduleKey) use ($featureRecords) {
            $featureKey = Arr::get($moduleConfig, 'feature', $moduleKey);
            $feature = $featureRecords->get($featureKey);

            return [
                $moduleKey => [
                    'module' => $moduleKey,
                    'feature' => $featureKey,
                    'label' => Arr::get($moduleConfig, 'label'),
                    'description' => Arr::get($moduleConfig, 'description'),
                    'is_enabled' => $feature
                        ? (bool) $feature->is_enabled
                        : (bool) Arr::get($moduleConfig, 'enabled_by_default', true),
                    'metadata' => $feature?->metadata ?? [
                        'label' => Arr::get($moduleConfig, 'label'),
                        'description' => Arr::get($moduleConfig, 'description'),
                    ],
                ],
            ];
        });
    }

    public function enrichPermissionsWithMetadata(Collection $permissions, ?Tenant $tenant = null): void
    {
        if ($permissions->isEmpty()) {
            return;
        }

        $modules = collect(config('permissions.modules', []));
        $featureStates = $tenant
            ? $this->featureStatesForTenant($tenant)->mapWithKeys(fn (array $state) => [$state['feature'] => $state])
            : collect();

        $permissions->each(function (Permission $permission) use ($modules, $featureStates): void {
            $moduleConfig = $modules->get($permission->module, []);
            $featureKey = Arr::get($moduleConfig, 'feature', $permission->module);
            $featureState = $featureStates->get($featureKey);

            $permission->setAttribute('module_metadata', [
                'key' => $permission->module,
                'label' => Arr::get($moduleConfig, 'label'),
                'description' => Arr::get($moduleConfig, 'description'),
                'feature' => $featureKey,
                'is_enabled' => $featureState['is_enabled'] ?? (bool) Arr::get($moduleConfig, 'enabled_by_default', true),
            ]);
        });
    }
}
