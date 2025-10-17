<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MemberCustomField */
class MemberCustomFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'data_type' => $this->data_type,
            'is_required' => $this->is_required,
            'is_active' => $this->is_active,
            'config' => $this->config,
        ];
    }
}
