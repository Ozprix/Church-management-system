<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerSignup extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'volunteer_role_id',
        'volunteer_team_id',
        'member_id',
        'name',
        'email',
        'phone',
        'status',
        'applied_at',
        'reviewed_at',
        'confirmed_at',
        'confirmed_by',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'metadata' => 'array',
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
