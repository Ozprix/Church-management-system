<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VolunteerAssignment extends Model
{
    use HasFactory;
    use TenantScoped;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'member_id',
        'volunteer_role_id',
        'volunteer_team_id',
        'gathering_id',
        'starts_at',
        'ends_at',
        'status',
        'notes',
        'confirmed_at',
        'confirmed_by',
        'repeat_frequency',
        'repeat_until',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'notes' => 'array',
        'repeat_until' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

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

    public function gathering(): BelongsTo
    {
        return $this->belongsTo(Gathering::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
