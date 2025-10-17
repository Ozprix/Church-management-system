<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Gathering */
class GatheringResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'location' => $this->location,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'service' => ServiceResource::make($this->whenLoaded('service')),
            'attendance' => $this->attendanceSummary(),
            'attendance_records' => AttendanceRecordResource::collection($this->whenLoaded('attendanceRecords')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, int>|null
     */
    private function attendanceSummary(): ?array
    {
        if (isset($this->attendance_total_count)) {
            return [
                'total' => (int) $this->attendance_total_count,
                'present' => (int) ($this->attendance_present_count ?? 0),
                'absent' => (int) ($this->attendance_absent_count ?? 0),
                'excused' => (int) ($this->attendance_excused_count ?? 0),
            ];
        }

        if ($this->relationLoaded('attendanceRecords')) {
            return [
                'total' => $this->attendanceRecords->count(),
                'present' => $this->attendanceRecords->where('status', 'present')->count(),
                'absent' => $this->attendanceRecords->where('status', 'absent')->count(),
                'excused' => $this->attendanceRecords->where('status', 'excused')->count(),
            ];
        }

        return null;
    }
}
