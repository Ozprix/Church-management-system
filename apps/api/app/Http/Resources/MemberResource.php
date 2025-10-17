<?php

namespace App\Http\Resources;

use App\Services\CustomFields\CustomFieldFileService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Member */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'preferred_name' => $this->preferred_name,
            'gender' => $this->gender,
            'dob' => optional($this->dob)?->toDateString(),
            'marital_status' => $this->marital_status,
            'membership_status' => $this->membership_status,
            'membership_stage' => $this->membership_stage,
            'joined_at' => optional($this->joined_at)?->toIso8601String(),
            'photo_path' => $this->photo_path,
            'notes' => $this->notes,
            'contacts' => MemberContactResource::collection($this->whenLoaded('contacts')),
            'families' => FamilyResource::collection($this->whenLoaded('families')),
            'custom_values' => $this->whenLoaded('customValues', function () {
                $fileService = app(CustomFieldFileService::class);

                return $this->customValues->map(function ($value) use ($fileService) {
                    $isFileType = in_array(optional($value->field)->data_type, ['file', 'signature'], true);
                    $displayValue = $value->value_json ?? $value->value_text ?? $value->value_string ?? $value->value_number ?? $value->value_boolean ?? $value->value_date;

                    if ($isFileType) {
                        $metadata = array_filter([
                            'disk' => $value->value_file_disk,
                            'path' => $value->value_file_path,
                            'name' => $value->value_file_name,
                            'mime' => $value->value_file_mime,
                            'size' => $value->value_file_size,
                        ], fn ($item) => !is_null($item));

                        $metadata['url'] = $fileService->temporaryUrl($value);
                        $displayValue = $metadata;
                    }

                    return [
                        'id' => $value->id,
                        'field_id' => $value->field_id,
                        'field' => $value->field ? [
                            'id' => $value->field->id,
                            'name' => $value->field->name,
                            'data_type' => $value->field->data_type,
                        ] : null,
                        'value' => $displayValue,
                        'raw' => [
                            'value_string' => $value->value_string,
                            'value_text' => $value->value_text,
                            'value_number' => $value->value_number,
                            'value_date' => optional($value->value_date)?->toDateString(),
                            'value_boolean' => $value->value_boolean,
                            'value_json' => $value->value_json,
                            'value_file_disk' => $value->value_file_disk,
                            'value_file_path' => $value->value_file_path,
                            'value_file_name' => $value->value_file_name,
                            'value_file_mime' => $value->value_file_mime,
                            'value_file_size' => $value->value_file_size,
                        ],
                    ];
                });
            }),
        ];
    }
}
