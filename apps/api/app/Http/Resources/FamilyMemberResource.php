<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FamilyMember */
class FamilyMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'member_id' => $this->member_id,
            'relationship' => $this->relationship,
            'is_primary_contact' => $this->is_primary_contact,
            'is_emergency_contact' => $this->is_emergency_contact,
            'member' => new MemberSummaryResource($this->whenLoaded('member')),
        ];
    }
}
