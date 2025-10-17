<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'monthly_price' => $this->monthly_price,
            'annual_price' => $this->annual_price,
            'currency' => $this->currency,
            'features' => $this->whenLoaded('features', function () {
                return $this->features->map(fn ($feature) => [
                    'feature' => $feature->feature,
                    'limit' => $feature->limit,
                    'metadata' => $feature->metadata,
                ]);
            }),
        ];
    }
}
