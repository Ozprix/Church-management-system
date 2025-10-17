<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MemberContact */
class MemberContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'value' => $this->value,
            'is_primary' => $this->is_primary,
            'is_emergency' => $this->is_emergency,
            'communication_preference' => $this->communication_preference,
        ];
    }
}
