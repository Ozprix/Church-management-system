<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerSignupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'member' => MemberSummaryResource::make($this->whenLoaded('member')),
            'role' => VolunteerRoleSummaryResource::make($this->whenLoaded('role')),
            'team' => VolunteerTeamResource::make($this->whenLoaded('team')),
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'applied_at' => optional($this->applied_at)?->toIso8601String(),
            'reviewed_at' => optional($this->reviewed_at)?->toIso8601String(),
            'confirmed_at' => optional($this->confirmed_at)?->toIso8601String(),
            'notes' => $this->notes,
            'metadata' => $this->metadata,
        ];
    }
}
