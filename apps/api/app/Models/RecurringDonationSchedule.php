<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringDonationSchedule extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'member_id',
        'payment_method_id',
        'frequency',
        'amount',
        'currency',
        'status',
        'starts_on',
        'ends_on',
        'next_run_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'next_run_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(RecurringDonationAttempt::class, 'schedule_id');
    }
}
