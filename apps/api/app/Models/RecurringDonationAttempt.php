<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringDonationAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'donation_id',
        'status',
        'amount',
        'currency',
        'provider',
        'provider_reference',
        'failure_reason',
        'processed_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RecurringDonationSchedule::class, 'schedule_id');
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }
}
