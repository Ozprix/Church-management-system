<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\VolunteerAssignment */
class VolunteerAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'notes' => $this->notes,
            'member' => MemberSummaryResource::make($this->whenLoaded('member')),
            'role' => VolunteerRoleSummaryResource::make($this->whenLoaded('role')),
            'team' => VolunteerTeamResource::make($this->whenLoaded('team')),
            'gathering' => GatheringResource::make($this->whenLoaded('gathering')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
