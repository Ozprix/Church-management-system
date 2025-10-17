<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RecurringDonationAttempt */
class RecurringDonationAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'schedule_id' => $this->schedule_id,
            'donation_id' => $this->donation_id,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'failure_reason' => $this->failure_reason,
            'processed_at' => optional($this->processed_at)?->toIso8601String(),
            'metadata' => $this->metadata,
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
