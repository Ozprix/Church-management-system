<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPlanUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'feature',
        'used',
        'limit',
        'metadata',
    ];

    protected $casts = [
        'used' => 'integer',
        'limit' => 'integer',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
