<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationMetric extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'collected_on',
        'channel',
        'queued',
        'sent',
        'delivered',
        'failed',
        'opened',
        'clicked',
        'metadata',
    ];

    protected $casts = [
        'collected_on' => 'date',
        'queued' => 'integer',
        'sent' => 'integer',
        'delivered' => 'integer',
        'failed' => 'integer',
        'opened' => 'integer',
        'clicked' => 'integer',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
