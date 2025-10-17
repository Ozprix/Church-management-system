<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'monthly_price',
        'annual_price',
        'currency',
        'is_active',
        'limits',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'limits' => 'array',
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function tenantPlans(): HasMany
    {
        return $this->hasMany(TenantPlan::class);
    }
}
