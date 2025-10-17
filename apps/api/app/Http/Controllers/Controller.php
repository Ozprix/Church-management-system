<?php

namespace App\Http\Controllers;

use App\Services\Rbac\RbacManager;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests;

    protected function authorizePermission(string $permission): void
    {
        $this->authorize($permission);
    }

    protected function ensureFeatureEnabled(string $feature): void
    {
        /** @var \App\Models\Tenant|null $tenant */
        $tenant = request()->attributes->get('tenant');

        if (! $tenant) {
            return;
        }

        /** @var RbacManager $manager */
        $manager = app(RbacManager::class);

        if (! $manager->featureEnabledForTenant($tenant->id, $feature)) {
            abort(403, __('This feature is disabled for the tenant.'));
        }
    }

    protected function request(): Request
    {
        return request();
    }
}
