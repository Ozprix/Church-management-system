<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorFollowupLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'followup_id',
        'step_id',
        'status',
        'channel',
        'run_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'run_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function followup(): BelongsTo
    {
        return $this->belongsTo(VisitorFollowup::class, 'followup_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(VisitorWorkflowStep::class, 'step_id');
    }
}
