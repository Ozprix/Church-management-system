<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationRule extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'trigger_type',
        'trigger_config',
        'channel',
        'notification_template_id',
        'delivery_config',
        'status',
        'throttle_minutes',
        'metadata',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'delivery_config' => 'array',
        'metadata' => 'array',
        'throttle_minutes' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(NotificationRuleRun::class);
    }
}
