<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberProcessRun extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'member_id',
        'process_id',
        'current_stage_id',
        'status',
        'started_at',
        'completed_at',
        'halted_at',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'halted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(MembershipProcess::class, 'process_id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(MembershipProcessStage::class, 'current_stage_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MembershipProcessLog::class, 'process_run_id');
    }
}
