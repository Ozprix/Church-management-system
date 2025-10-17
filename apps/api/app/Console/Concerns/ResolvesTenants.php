<?php

namespace App\Console\Concerns;

use App\Models\Tenant;

trait ResolvesTenants
{
    protected function resolveTenant(string|int $identifier): ?Tenant
    {
        return Tenant::query()
            ->where('id', $identifier)
            ->orWhere('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->first();
    }
}
