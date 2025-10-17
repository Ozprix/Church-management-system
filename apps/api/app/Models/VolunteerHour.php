<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerHour extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'volunteer_assignment_id',
        'member_id',
        'served_on',
        'hours',
        'source',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'served_on' => 'date',
        'hours' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(VolunteerAssignment::class, 'volunteer_assignment_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
