<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAnalyticsReportSnapshot extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'attendance_analytics_report_id',
        'disk',
        'file_path',
        'format',
        'filters',
        'generated_at',
        'expires_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(AttendanceAnalyticsReport::class, 'attendance_analytics_report_id');
    }
}

