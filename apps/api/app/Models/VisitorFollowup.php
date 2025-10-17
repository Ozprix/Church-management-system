<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitorFollowup extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'member_id',
        'workflow_id',
        'current_step_id',
        'status',
        'started_at',
        'next_run_at',
        'completed_at',
        'last_step_run_at',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'next_run_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_step_run_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(VisitorWorkflow::class, 'workflow_id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(VisitorWorkflowStep::class, 'current_step_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(VisitorFollowupLog::class, 'followup_id');
    }
}
