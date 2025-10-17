<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Service extends Model
{
    use HasFactory;
    use TenantScoped;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'short_code',
        'description',
        'default_location',
        'default_start_time',
        'default_duration_minutes',
        'absence_threshold',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $service): void {
            if (empty($service->slug)) {
                $service->slug = Str::slug(Str::limit($service->name, 60, ''));
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function gatherings(): HasMany
    {
        return $this->hasMany(Gathering::class);
    }

    protected function shortCode(): Attribute
    {
        return Attribute::make(
            set: function (?string $value) {
                return $value ? strtoupper($value) : null;
            }
        );
    }
}
