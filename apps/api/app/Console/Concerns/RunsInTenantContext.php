<?php

namespace App\Console\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy\TenantManager;

trait RunsInTenantContext
{
    /**
     * @template TReturn
     *
     * @param callable(TenantManager):TReturn $callback
     * @return TReturn
     */
    protected function runInTenantContext(Tenant $tenant, callable $callback)
    {
        /** @var TenantManager $manager */
        $manager = app(TenantManager::class);
        $originalTenant = $manager->getTenant();
        $manager->setTenant($tenant);

        try {
            return $callback($manager);
        } finally {
            $this->restoreTenantContext($manager, $originalTenant);
        }
    }

    protected function restoreTenantContext(TenantManager $manager, ?Tenant $originalTenant): void
    {
        if ($originalTenant) {
            $manager->setTenant($originalTenant);

            return;
        }

        $manager->forgetTenant();
    }
}
