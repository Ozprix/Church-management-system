<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Family */
class FamilyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_name' => $this->family_name,
            'photo_path' => $this->photo_path,
            'notes' => $this->notes,
            'address' => $this->address,
            'members_count' => $this->whenCounted('members', $this->members_count ?? 0),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'members' => MemberSummaryResource::collection($this->whenLoaded('members')),
            'pivot' => $this->whenPivotLoaded('family_members', function () {
                return [
                    'relationship' => $this->pivot->relationship,
                    'is_primary_contact' => (bool) $this->pivot->is_primary_contact,
                    'is_emergency_contact' => (bool) $this->pivot->is_emergency_contact,
                ];
            }),
        ];
    }
}
