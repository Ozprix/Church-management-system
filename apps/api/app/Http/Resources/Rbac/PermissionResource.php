<?php

namespace App\Http\Resources\Rbac;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class PermissionResource extends JsonResource
{
    public function toArray($request): array
    {
        $moduleMetadata = (array) $this->resource->getAttribute('module_metadata');

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'module' => [
                'key' => Arr::get($moduleMetadata, 'key', $this->module),
                'label' => Arr::get($moduleMetadata, 'label'),
                'description' => Arr::get($moduleMetadata, 'description'),
                'feature' => Arr::get($moduleMetadata, 'feature'),
                'is_enabled' => Arr::get($moduleMetadata, 'is_enabled'),
            ],
        ];
    }
}
