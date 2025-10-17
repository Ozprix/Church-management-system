<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class VolunteerRole extends Model
{
    use HasFactory;
    use TenantScoped;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'skills_required',
        'active_assignment_count',
        'pending_signup_count',
    ];

    protected $casts = [
        'skills_required' => 'array',
        'active_assignment_count' => 'integer',
        'pending_signup_count' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $role): void {
            if (empty($role->slug)) {
                $role->slug = Str::slug(Str::limit($role->name, 60, ''));
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(VolunteerTeam::class, 'volunteer_role_team')->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VolunteerAssignment::class);
    }
}
