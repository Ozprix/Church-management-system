<?php

namespace App\Models;

use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipProcessStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_id',
        'key',
        'name',
        'step_order',
        'entry_actions',
        'exit_actions',
        'reminder_minutes',
        'reminder_template_id',
        'metadata',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'entry_actions' => 'array',
        'exit_actions' => 'array',
        'reminder_minutes' => 'integer',
        'metadata' => 'array',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(MembershipProcess::class, 'process_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MembershipProcessLog::class, 'stage_id');
    }

    public function reminderTemplate(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'reminder_template_id');
    }
}
