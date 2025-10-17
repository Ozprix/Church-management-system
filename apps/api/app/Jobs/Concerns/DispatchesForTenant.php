<?php

namespace App\Jobs\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy\TenantManager;
use Illuminate\Support\Facades\App;

/**
 * Provides helpers to dispatch jobs with an explicit tenant context.
 */
trait DispatchesForTenant
{
    public static function dispatchForTenant(int|Tenant $tenant, ...$arguments): mixed
    {
        /** @var TenantManager $manager */
        $manager = App::make(TenantManager::class);

        $tenantInstance = $tenant instanceof Tenant
            ? $tenant
            : Tenant::query()->findOrFail($tenant);

        $originalTenant = $manager->getTenant();
        $manager->setTenant($tenantInstance);

        try {
            if (config('queue.default') === 'sync') {
                return static::dispatchSync(...$arguments);
            }

            return static::dispatch(...$arguments);
        } finally {
            if ($originalTenant) {
                $manager->setTenant($originalTenant);
            } else {
                $manager->forgetTenant();
            }
        }
    }
}
