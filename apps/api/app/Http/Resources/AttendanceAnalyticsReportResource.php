<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AttendanceAnalyticsReport */
class AttendanceAnalyticsReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'filters' => $this->filters,
            'frequency' => $this->frequency,
            'channel' => $this->channel,
            'email_recipient' => $this->email_recipient,
            'snapshots_count' => $this->when(isset($this->snapshots_count), (int) $this->snapshots_count),
            'latest_snapshot' => $this->when(
                $this->relationLoaded('latestSnapshot') && $this->latestSnapshot,
                fn () => [
                    'id' => $this->latestSnapshot->id,
                    'format' => $this->latestSnapshot->format,
                    'generated_at' => optional($this->latestSnapshot->generated_at)?->toIso8601String(),
                    'expires_at' => optional($this->latestSnapshot->expires_at)?->toIso8601String(),
                ]
            ),
            'last_run_at' => optional($this->last_run_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
