<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\GatheringRegistration */
class GatheringRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gathering_id' => $this->gathering_id,
            'ticket_type_id' => $this->ticket_type_id,
            'member_id' => $this->member_id,
            'status' => $this->status,
            'quantity' => $this->quantity,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'ticket_type' => GatheringTicketTypeResource::make($this->whenLoaded('ticketType')),
            'member' => MemberSummaryResource::make($this->whenLoaded('member')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
