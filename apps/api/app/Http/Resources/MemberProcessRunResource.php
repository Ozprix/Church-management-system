<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberProcessRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'process' => MembershipProcessResource::make($this->whenLoaded('process')),
            'current_stage' => MembershipProcessStageResource::make($this->whenLoaded('currentStage')),
            'status' => $this->status,
            'started_at' => optional($this->started_at)?->toIso8601String(),
            'completed_at' => optional($this->completed_at)?->toIso8601String(),
            'halted_at' => optional($this->halted_at)?->toIso8601String(),
            'metadata' => $this->metadata,
            'logs' => MembershipProcessLogResource::collection($this->whenLoaded('logs')),
        ];
    }
}
