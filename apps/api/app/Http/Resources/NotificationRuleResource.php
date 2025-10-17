<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'trigger_type' => $this->trigger_type,
            'trigger_config' => $this->trigger_config,
            'channel' => $this->channel,
            'template' => NotificationTemplateResource::make($this->whenLoaded('template')),
            'delivery_config' => $this->delivery_config,
            'status' => $this->status,
            'throttle_minutes' => $this->throttle_minutes,
            'metadata' => $this->metadata,
            'runs' => NotificationRuleRunResource::collection($this->whenLoaded('runs')),
        ];
    }
}
