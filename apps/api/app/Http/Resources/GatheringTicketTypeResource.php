<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\GatheringTicketType */
class GatheringTicketTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gathering_id' => $this->gathering_id,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'price' => $this->price,
            'currency' => $this->currency,
            'sales_start_at' => $this->sales_start_at?->toIso8601String(),
            'sales_end_at' => $this->sales_end_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
