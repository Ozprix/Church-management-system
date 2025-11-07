<?php

namespace App\Http\Controllers\Api\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Resources\Rbac\RoleResource;
use App\Models\Role;
use App\Services\Rbac\RbacManager;
use App\Support\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function index(TenantManager $tenantManager, RbacManager $rbacManager): JsonResponse
    {
        $tenant = $tenantManager->getTenant();

        $roles = Role::query()
            ->with(['permissions'])
            ->withCount('users')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        if ($tenant) {
            $rbacManager->enrichPermissionsWithMetadata(
                $roles->flatMap(fn (Role $role) => $role->permissions),
                $tenant
            );

            $features = $rbacManager->featureStatesForTenant($tenant);
        } else {
            $rbacManager->enrichPermissionsWithMetadata($roles->flatMap(fn (Role $role) => $role->permissions));
            $features = collect();
        }

        return RoleResource::collection($roles)
            ->additional([
                'meta' => [
                    'features' => $features->values(),
                ],
            ])
            ->response();
    }
}
