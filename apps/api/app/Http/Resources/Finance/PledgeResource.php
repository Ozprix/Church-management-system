<?php

namespace App\Http\Resources\Finance;

use App\Http\Resources\MemberSummaryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Pledge */
class PledgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'fulfilled_amount' => $this->fulfilled_amount,
            'currency' => $this->currency,
            'frequency' => $this->frequency,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'member' => MemberSummaryResource::make($this->whenLoaded('member')),
            'fund' => FundResource::make($this->whenLoaded('fund')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
