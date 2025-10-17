<?php

namespace App\Http\Requests\Volunteer;

use Illuminate\Foundation\Http\FormRequest;

class StoreVolunteerHourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('volunteer_pipeline.manage_hours') ?? false;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'volunteer_assignment_id' => ['nullable', 'integer', 'exists:volunteer_assignments,id'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'served_on' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0'],
            'source' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
