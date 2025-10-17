<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationRuleRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_rule_id',
        'ran_at',
        'matched_count',
        'sent_count',
        'status',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'ran_at' => 'datetime',
        'matched_count' => 'integer',
        'sent_count' => 'integer',
        'metadata' => 'array',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(NotificationRule::class, 'notification_rule_id');
    }
}
