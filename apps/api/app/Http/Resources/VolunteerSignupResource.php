<?php

namespace App\Http\Resources;

use App\Support\VolunteerSignupStage;
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
            'stage' => $this->stage,
            'stage_label' => \App\Support\VolunteerSignupStage::label($this->stage),
            'applied_at' => optional($this->applied_at)?->toIso8601String(),
            'reviewed_at' => optional($this->reviewed_at)?->toIso8601String(),
            'confirmed_at' => optional($this->confirmed_at)?->toIso8601String(),
            'last_contacted_at' => optional($this->last_contacted_at)?->toIso8601String(),
            'follow_up_at' => optional($this->follow_up_at)?->toIso8601String(),
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'stage_history' => $this->stage_history,
            'onboarding_checklist' => $this->onboarding_checklist,
        ];
    }
}
