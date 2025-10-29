<?php

namespace App\Services\Security;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantSecurityPolicy;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class TenantSecurityPolicyService
{
    public function initializeForTenant(Tenant $tenant): TenantSecurityPolicy
    {
        return TenantSecurityPolicy::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'enforce_two_factor' => false,
                'enforced_role_slugs' => $this->defaultRoleSlugs(),
                'enforced_permission_slugs' => $this->defaultPermissionSlugs(),
            ]
        );
    }

    public function getPolicyForTenant(Tenant $tenant): TenantSecurityPolicy
    {
        /** @var TenantSecurityPolicy $policy */
        $policy = TenantSecurityPolicy::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'enforce_two_factor' => false,
                'enforced_role_slugs' => $this->defaultRoleSlugs(),
                'enforced_permission_slugs' => $this->defaultPermissionSlugs(),
            ]
        );

        return $policy;
    }

    public function updatePolicy(Tenant $tenant, array $attributes): TenantSecurityPolicy
    {
        $policy = $this->getPolicyForTenant($tenant);

        if (array_key_exists('enforce_two_factor', $attributes)) {
            $policy->enforce_two_factor = (bool) $attributes['enforce_two_factor'];
        }

        if (array_key_exists('enforced_role_slugs', $attributes)) {
            $policy->enforced_role_slugs = array_values(array_unique(Arr::get($attributes, 'enforced_role_slugs', [])));
        }

        if (array_key_exists('enforced_permission_slugs', $attributes)) {
            $policy->enforced_permission_slugs = array_values(array_unique(Arr::get($attributes, 'enforced_permission_slugs', [])));
        }

        $policy->save();

        return $policy->refresh();
    }

    public function requiresTwoFactor(User $user, ?TenantSecurityPolicy $policy = null): bool
    {
        if ($user->tenant === null) {
            return false;
        }

        $policy ??= $this->getPolicyForTenant($user->tenant);

        if (! $policy->enforce_two_factor) {
            return false;
        }

        $user->loadMissing('roles', 'roles.permissions', 'permissions');

        $roleSlugs = $user->roles->pluck('slug');

        $enforcedRoles = Collection::make($policy->enforced_role_slugs ?? [])
            ->filter();

        if ($roleSlugs->intersect($enforcedRoles)->isNotEmpty()) {
            return true;
        }

        $permissionSlugs = $user->allPermissionSlugs();
        $enforcedPermissions = Collection::make($policy->enforced_permission_slugs ?? [])
            ->filter();

        if ($permissionSlugs->intersect($enforcedPermissions)->isNotEmpty()) {
            return true;
        }

        return false;
    }

    public function buildComplianceReport(Tenant $tenant): array
    {
        $policy = $this->getPolicyForTenant($tenant);

        $users = $tenant->users()
            ->with([
                'roles',
                'permissions',
                'tokens' => fn ($query) => $query->latest('last_used_at')->limit(1),
            ])
            ->orderBy('name')
            ->get();

        $entries = $users->map(function (User $user) use ($policy, $tenant) {
            $user->setRelation('tenant', $tenant);
            $requiresTwoFactor = $this->requiresTwoFactor($user, $policy);
            $latestToken = $user->tokens->first();
            $lastUsedAt = $latestToken?->last_used_at;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('slug')->all(),
                'requires_two_factor' => $requiresTwoFactor,
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
                'two_factor_confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
                'last_login_at' => $lastUsedAt?->toIso8601String(),
            ];
        });

        $enforcedUsers = $entries->filter(fn (array $entry) => $entry['requires_two_factor']);
        $nonCompliant = $enforcedUsers->filter(fn (array $entry) => ! $entry['two_factor_enabled']);

        return [
            'policy' => [
                'enforce_two_factor' => $policy->enforce_two_factor,
                'enforced_role_slugs' => $policy->enforced_role_slugs ?? [],
                'enforced_permission_slugs' => $policy->enforced_permission_slugs ?? [],
            ],
            'summary' => [
                'total_users' => $entries->count(),
                'enforced_user_count' => $enforcedUsers->count(),
                'compliant_user_count' => $enforcedUsers->count() - $nonCompliant->count(),
                'non_compliant_user_count' => $nonCompliant->count(),
            ],
            'entries' => $entries->all(),
            'non_compliant_users' => $nonCompliant->values()->all(),
        ];
    }

    public function validateRolesBelongToTenant(Tenant $tenant, array $roleSlugs): bool
    {
        if (empty($roleSlugs)) {
            return true;
        }

        $count = Role::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('slug', $roleSlugs)
            ->count();

        return $count === count(array_unique($roleSlugs));
    }

    public function validatePermissionsExist(array $permissionSlugs): bool
    {
        if (empty($permissionSlugs)) {
            return true;
        }

        $count = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->count();

        return $count === count(array_unique($permissionSlugs));
    }

    private function defaultRoleSlugs(): array
    {
        return array_values(array_unique(config('security.two_factor.default_enforced_roles', [])));
    }

    private function defaultPermissionSlugs(): array
    {
        return array_values(array_unique(config('security.two_factor.default_enforced_permissions', [])));
    }
}
