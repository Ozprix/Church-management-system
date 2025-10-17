<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipProcessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_run_id',
        'stage_id',
        'actor_id',
        'status',
        'notes',
        'metadata',
        'logged_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'logged_at' => 'datetime',
    ];

    public function processRun(): BelongsTo
    {
        return $this->belongsTo(MemberProcessRun::class, 'process_run_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(MembershipProcessStage::class, 'stage_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
