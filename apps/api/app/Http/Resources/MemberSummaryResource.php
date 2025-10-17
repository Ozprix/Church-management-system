<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Member */
class MemberSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'preferred_name' => $this->preferred_name,
            'membership_status' => $this->membership_status,
            'membership_stage' => $this->membership_stage,
            'preferred_contact' => $this->whenLoaded('contacts', function () {
                $contact = $this->preferredContact();

                if (! $contact) {
                    return null;
                }

                return [
                    'id' => $contact->id,
                    'type' => $contact->type,
                    'label' => $contact->label,
                    'value' => $contact->value,
                    'is_primary' => (bool) $contact->is_primary,
                ];
            }),
        ];
    }
}
