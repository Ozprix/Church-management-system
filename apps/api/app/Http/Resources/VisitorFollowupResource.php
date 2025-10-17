<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\VisitorFollowup */
class VisitorFollowupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'workflow_id' => $this->workflow_id,
            'current_step_id' => $this->current_step_id,
            'status' => $this->status,
            'started_at' => optional($this->started_at)?->toIso8601String(),
            'next_run_at' => optional($this->next_run_at)?->toIso8601String(),
            'completed_at' => optional($this->completed_at)?->toIso8601String(),
            'last_step_run_at' => optional($this->last_step_run_at)?->toIso8601String(),
            'metadata' => $this->metadata,
            'current_step' => VisitorWorkflowStepResource::make($this->whenLoaded('currentStep')),
            'workflow' => VisitorWorkflowResource::make($this->whenLoaded('workflow')),
            'logs' => VisitorFollowupLogResource::collection($this->whenLoaded('logs')),
        ];
    }
}
