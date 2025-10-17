<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VolunteerAvailability extends Model
{
    use HasFactory;
    use TenantScoped;
    use SoftDeletes;

    protected $table = 'volunteer_availability';

    protected $fillable = [
        'tenant_id',
        'member_id',
        'weekdays',
        'time_blocks',
        'unavailable_from',
        'unavailable_until',
        'notes',
    ];

    protected $casts = [
        'weekdays' => 'array',
        'time_blocks' => 'array',
        'unavailable_from' => 'date',
        'unavailable_until' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
