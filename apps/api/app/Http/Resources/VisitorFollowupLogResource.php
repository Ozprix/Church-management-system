<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\VisitorFollowupLog */
class VisitorFollowupLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'channel' => $this->channel,
            'notes' => $this->notes,
            'run_at' => optional($this->run_at)->toIso8601String(),
            'step' => $this->whenLoaded('step', function () {
                return [
                    'id' => $this->step->id,
                    'name' => $this->step->name,
                    'channel' => $this->step->channel,
                    'step_number' => $this->step->step_number,
                ];
            }),
            'metadata' => $this->metadata,
        ];
    }
}
