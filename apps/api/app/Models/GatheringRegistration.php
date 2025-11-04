<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatheringRegistration extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'gathering_id',
        'ticket_type_id',
        'member_id',
        'status',
        'quantity',
        'name',
        'email',
        'phone',
        'amount',
        'currency',
        'checked_in_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'amount' => 'decimal:2',
        'checked_in_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function gathering(): BelongsTo
    {
        return $this->belongsTo(Gathering::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(GatheringTicketType::class, 'ticket_type_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
