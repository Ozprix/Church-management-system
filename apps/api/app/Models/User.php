<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use TenantScoped;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_recovery_codes' => 'array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! empty($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withTimestamps()
            ->withPivot(['assigned_at', 'assigned_by', 'metadata']);
    }

    public function permissions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function hasRole(string $roleSlug): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->slug === $roleSlug);
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($permissionSlug === '*') {
            return true;
        }

        $permissions = $this->allPermissionSlugs();

        if ($permissions->contains($permissionSlug)) {
            return true;
        }

        $superPermission = config('permissions.super_permission', 'system.super_admin');

        return $permissions->contains($superPermission);
    }

    public function allPermissionSlugs(): \Illuminate\Support\Collection
    {
        $this->loadMissing('roles.permissions', 'permissions');

        $direct = $this->permissions->pluck('slug');
        $viaRoles = $this->roles->flatMap(fn (Role $role) => $role->permissions->pluck('slug'));

        return $direct->merge($viaRoles)->unique()->values();
    }
}
