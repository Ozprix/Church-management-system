<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MemberAnalyticsReport */
class MemberAnalyticsReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'filters' => $this->filters,
            'frequency' => $this->frequency,
            'channel' => $this->channel,
            'email_recipient' => $this->email_recipient,
            'last_run_at' => optional($this->last_run_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
