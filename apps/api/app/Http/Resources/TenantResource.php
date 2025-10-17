<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'plan' => $this->plan,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'meta' => $this->meta,
            'domains' => TenantDomainResource::collection($this->whenLoaded('domains')),
        ];
    }
}
