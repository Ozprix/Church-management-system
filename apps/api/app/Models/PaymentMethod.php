<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use HasFactory;
    use TenantScoped;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'member_id',
        'type',
        'brand',
        'last_four',
        'provider',
        'provider_reference',
        'expires_at',
        'is_default',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'is_default' => 'boolean',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }
}
