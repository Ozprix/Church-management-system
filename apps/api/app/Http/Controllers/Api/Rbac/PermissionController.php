<?php

namespace App\Http\Controllers\Api\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Resources\Rbac\PermissionResource;
use App\Models\Permission;
use App\Services\Rbac\RbacManager;
use App\Support\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(TenantManager $tenantManager, RbacManager $rbacManager): JsonResponse
    {
        $tenant = $tenantManager->getTenant();

        $permissions = Permission::query()
            ->orderBy('module')
            ->orderBy('name')
            ->get();

        if ($tenant) {
            $rbacManager->enrichPermissionsWithMetadata($permissions, $tenant);
            $features = $rbacManager->featureStatesForTenant($tenant);
        } else {
            $rbacManager->enrichPermissionsWithMetadata($permissions);
            $features = collect();
        }

        return PermissionResource::collection($permissions)
            ->additional([
                'meta' => [
                    'features' => $features->values(),
                ],
            ])
            ->response();
    }
}
