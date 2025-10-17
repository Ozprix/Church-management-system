<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipProcessLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stage_id' => $this->stage_id,
            'status' => $this->status,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'logged_at' => optional($this->logged_at)?->toIso8601String(),
        ];
    }
}
