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

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_CANCELLED,
        self::STATUS_CHECKED_IN,
    ];

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
