<?php

namespace App\Http\Requests\Volunteer;

use Illuminate\Foundation\Http\FormRequest;

class StoreVolunteerSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('volunteer_pipeline.manage_signups') ?? false;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'volunteer_role_id' => ['nullable', 'integer', 'exists:volunteer_roles,id'],
            'volunteer_team_id' => ['nullable', 'integer', 'exists:volunteer_teams,id'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
