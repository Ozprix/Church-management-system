<?php

namespace App\Http\Resources\Finance;

use App\Http\Resources\MemberSummaryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Donation */
class DonationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'received_at' => $this->received_at?->toIso8601String(),
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'receipt_number' => $this->receipt_number,
            'notes' => $this->notes,
            'member' => MemberSummaryResource::make($this->whenLoaded('member')),
            'items' => DonationItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
