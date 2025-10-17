<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Gathering extends Model
{
    use HasFactory;
    use TenantScoped;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'service_id',
        'uuid',
        'name',
        'status',
        'starts_at',
        'ends_at',
        'location',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $gathering): void {
            if (empty($gathering->uuid)) {
                $gathering->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'attendance_records')->withPivot([
            'status',
            'check_in_method',
            'checked_in_at',
            'checked_out_at',
            'notes',
            'created_at',
            'updated_at',
        ]);
    }
}
