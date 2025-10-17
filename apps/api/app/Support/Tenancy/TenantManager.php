<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Illuminate\Contracts\Support\Arrayable;

class TenantManager implements Arrayable
{
    private ?Tenant $tenant = null;

    public function setTenant(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function forgetTenant(): void
    {
        $this->tenant = null;
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function toArray(): array
    {
        return $this->tenant ? $this->tenant->toArray() : [];
    }
}
