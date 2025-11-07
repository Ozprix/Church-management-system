<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AttendanceAnalyticsReport extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'filters',
        'frequency',
        'channel',
        'email_recipient',
        'last_run_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'last_run_at' => 'datetime',
    ];

    public const FREQUENCIES = ['none', 'daily', 'weekly', 'monthly'];
    public const CHANNELS = ['email', 'download', 'both'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(AttendanceAnalyticsReportSnapshot::class);
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(AttendanceAnalyticsReportSnapshot::class)->latestOfMany('generated_at');
    }

    public function isScheduled(): bool
    {
        return $this->frequency !== 'none';
    }
}
