<?php

namespace App\Models\Concerns;

use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\App;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var TenantManager $manager */
        $manager = App::make(TenantManager::class);

        if (!$manager->hasTenant()) {
            return;
        }

        $builder->where($model->getTable() . '.' . $model->getTenantForeignKey(), $manager->getTenant()->getKey());
    }
}

trait TenantScoped
{
    public static function bootTenantScoped(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model) {
            /** @var TenantManager $manager */
            $manager = App::make(TenantManager::class);
            if ($manager->hasTenant() && empty($model->{$model->getTenantForeignKey()})) {
                $model->{$model->getTenantForeignKey()} = $manager->getTenant()->getKey();
            }
        });
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->where($this->getTable() . '.' . $this->getTenantForeignKey(), $tenantId);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class, $this->getTenantForeignKey());
    }

    public function getTenantForeignKey(): string
    {
        return property_exists($this, 'tenantForeignKey') ? $this->tenantForeignKey : 'tenant_id';
    }
}
