<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AttendanceAnalyticsReportSnapshot */
class AttendanceAnalyticsReportSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'format' => $this->format,
            'generated_at' => optional($this->generated_at)?->toIso8601String(),
            'expires_at' => optional($this->expires_at)?->toIso8601String(),
            'filters' => $this->filters,
        ];
    }
}

