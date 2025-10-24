<?php

namespace App\Http\Resources;

use App\Support\VolunteerSignupStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\VolunteerRole */
class VolunteerRoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stageCounts = collect($this->pipeline_stage_counts ?? [])
            ->map(fn ($count, $stage) => [
                'stage' => $stage,
                'label' => VolunteerSignupStage::label($stage),
                'count' => (int) $count,
            ])
            ->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'skills_required' => $this->skills_required,
            'analytics' => [
                'active_assignment_count' => $this->active_assignment_count,
                'pending_signup_count' => $this->pending_signup_count,
                'pipeline_stage_counts' => $stageCounts,
            ],
            'teams' => VolunteerTeamResource::collection($this->whenLoaded('teams')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
