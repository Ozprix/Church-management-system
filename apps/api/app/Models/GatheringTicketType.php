<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GatheringTicketType extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'gathering_id',
        'name',
        'capacity',
        'price',
        'currency',
        'sales_start_at',
        'sales_end_at',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'capacity' => 'integer',
        'sales_start_at' => 'datetime',
        'sales_end_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function gathering(): BelongsTo
    {
        return $this->belongsTo(Gathering::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(GatheringRegistration::class, 'ticket_type_id');
    }
}
