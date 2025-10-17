<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\VolunteerAvailability */
class VolunteerAvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member' => MemberSummaryResource::make($this->whenLoaded('member')),
            'weekdays' => $this->weekdays,
            'time_blocks' => $this->time_blocks,
            'unavailable_from' => $this->unavailable_from?->toDateString(),
            'unavailable_until' => $this->unavailable_until?->toDateString(),
            'notes' => $this->notes,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
