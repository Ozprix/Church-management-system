<?php

namespace App\Http\Requests\Volunteer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVolunteerTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $team = $this->route('volunteer_team');
        $tenantId = optional($this->attributes->get('tenant'))->id;

        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', Rule::unique('volunteer_teams', 'slug')->ignore($team?->id)->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer'],
        ];
    }
}
