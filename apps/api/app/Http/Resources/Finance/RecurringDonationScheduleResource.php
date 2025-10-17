<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RecurringDonationSchedule */
class RecurringDonationScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'payment_method_id' => $this->payment_method_id,
            'frequency' => $this->frequency,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'starts_on' => optional($this->starts_on)?->toDateString(),
            'ends_on' => optional($this->ends_on)?->toDateString(),
            'next_run_at' => optional($this->next_run_at)?->toIso8601String(),
            'metadata' => $this->metadata,
            'member' => $this->whenLoaded('member', fn () => [
                'id' => $this->member->id,
                'first_name' => $this->member->first_name,
                'last_name' => $this->member->last_name,
            ]),
            'payment_method' => $this->whenLoaded('paymentMethod', fn () => [
                'id' => $this->paymentMethod->id,
                'type' => $this->paymentMethod->type,
                'brand' => $this->paymentMethod->brand,
                'last_four' => $this->paymentMethod->last_four,
            ]),
        ];
    }
}
