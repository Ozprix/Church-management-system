<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationRuleRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ran_at' => optional($this->ran_at)?->toIso8601String(),
            'matched_count' => $this->matched_count,
            'sent_count' => $this->sent_count,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata,
        ];
    }
}
