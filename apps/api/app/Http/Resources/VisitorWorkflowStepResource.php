<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\VisitorWorkflowStep */
class VisitorWorkflowStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'step_number' => $this->step_number,
            'name' => $this->name,
            'delay_minutes' => $this->delay_minutes,
            'channel' => $this->channel,
            'notification_template_id' => $this->notification_template_id,
            'metadata' => $this->metadata,
            'is_active' => $this->is_active,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
