<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory;
    use TenantScoped;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REIMBURSED = 'reimbursed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REIMBURSED,
    ];

    protected $fillable = [
        'tenant_id',
        'submitted_by',
        'approved_by',
        'reimbursed_by',
        'title',
        'description',
        'total_amount',
        'currency',
        'status',
        'incurred_at',
        'submitted_at',
        'approved_at',
        'reimbursed_at',
        'metadata',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'incurred_at' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'reimbursed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(ExpenseLineItem::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reimburser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reimbursed_by');
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
