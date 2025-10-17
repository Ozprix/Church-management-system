<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipProcess extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'is_active',
        'auto_start_on_member_create',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_start_on_member_create' => 'boolean',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(MembershipProcessStage::class, 'process_id')->orderBy('step_order');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(MemberProcessRun::class, 'process_id');
    }
}
