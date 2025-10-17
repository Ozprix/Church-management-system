<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipProcessStageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'step_order' => $this->step_order,
            'entry_actions' => $this->entry_actions,
            'exit_actions' => $this->exit_actions,
            'reminder_minutes' => $this->reminder_minutes,
            'reminder_template_id' => $this->reminder_template_id,
            'metadata' => $this->metadata,
        ];
    }
}
