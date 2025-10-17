<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\VisitorFollowupLog */
class VisitorFollowupLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'step_id' => $this->step_id,
            'status' => $this->status,
            'channel' => $this->channel,
            'run_at' => optional($this->run_at)?->toIso8601String(),
            'notes' => $this->notes,
            'metadata' => $this->metadata,
        ];
    }
}
