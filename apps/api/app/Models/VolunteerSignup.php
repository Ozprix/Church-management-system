<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use App\Support\VolunteerSignupStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerSignup extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $attributes = [
        'stage' => VolunteerSignupStage::APPLIED,
    ];

    protected $fillable = [
        'tenant_id',
        'volunteer_role_id',
        'volunteer_team_id',
        'member_id',
        'name',
        'email',
        'phone',
        'status',
        'stage',
        'applied_at',
        'reviewed_at',
        'confirmed_at',
        'confirmed_by',
        'last_contacted_at',
        'follow_up_at',
        'notes',
        'metadata',
        'stage_history',
        'onboarding_checklist',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'metadata' => 'array',
        'stage_history' => 'array',
        'onboarding_checklist' => 'array',
        'last_contacted_at' => 'datetime',
        'follow_up_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(VolunteerRole::class, 'volunteer_role_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(VolunteerTeam::class, 'volunteer_team_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
