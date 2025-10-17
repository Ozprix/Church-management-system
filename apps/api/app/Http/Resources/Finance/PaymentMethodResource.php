<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'member_id' => $this->member_id,
            'member' => $this->whenLoaded('member', function () {
                return [
                    'id' => $this->member?->id,
                    'first_name' => $this->member?->first_name,
                    'last_name' => $this->member?->last_name,
                ];
            }),
            'type' => $this->type,
            'brand' => $this->brand,
            'last_four' => $this->last_four,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'expires_at' => optional($this->expires_at)->toDateString(),
            'is_default' => (bool) $this->is_default,
            'metadata' => $this->metadata,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
