<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerHourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'member' => MemberSummaryResource::make($this->whenLoaded('member')),
            'assignment_id' => $this->volunteer_assignment_id,
            'served_on' => optional($this->served_on)?->toDateString(),
            'hours' => number_format((float) $this->hours, 2, '.', ''),
            'source' => $this->source,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
        ];
    }
}
