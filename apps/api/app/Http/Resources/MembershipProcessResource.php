<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipProcessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'auto_start_on_member_create' => $this->auto_start_on_member_create,
            'metadata' => $this->metadata,
            'stages' => MembershipProcessStageResource::collection($this->whenLoaded('stages')),
        ];
    }
}
