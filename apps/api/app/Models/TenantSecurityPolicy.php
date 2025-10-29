<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSecurityPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'enforce_two_factor',
        'enforced_role_slugs',
        'enforced_permission_slugs',
    ];

    protected $casts = [
        'enforce_two_factor' => 'boolean',
        'enforced_role_slugs' => 'array',
        'enforced_permission_slugs' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
