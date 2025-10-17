<?php

namespace App\Http\Requests\Volunteer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVolunteerSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('volunteer_pipeline.manage_signups') ?? false;
    }

    public function rules(): array
    {
        return [
            'volunteer_role_id' => ['nullable', 'integer', 'exists:volunteer_roles,id'],
            'volunteer_team_id' => ['nullable', 'integer', 'exists:volunteer_teams,id'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:pending,reviewed,confirmed,assigned,rejected'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'assignment' => ['nullable', 'array'],
            'assignment.starts_at' => ['nullable', 'date'],
            'assignment.ends_at' => ['nullable', 'date', 'after_or_equal:assignment.starts_at'],
            'assignment.gathering_id' => ['nullable', 'integer', 'exists:gatherings,id'],
            'assignment.volunteer_team_id' => ['nullable', 'integer', 'exists:volunteer_teams,id'],
            'assignment.member_id' => ['nullable', 'integer', 'exists:members,id'],
        ];
    }
}
