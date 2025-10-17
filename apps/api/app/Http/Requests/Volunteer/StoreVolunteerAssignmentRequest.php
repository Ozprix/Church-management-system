<?php

namespace App\Http\Requests\Volunteer;

use Illuminate\Foundation\Http\FormRequest;

class StoreVolunteerAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($tenant = $this->attributes->get('tenant')) {
            $this->merge(['tenant_id' => $tenant->id]);
        }
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer'],
            'member_id' => ['required', 'integer'],
            'volunteer_role_id' => ['required', 'integer'],
            'volunteer_team_id' => ['nullable', 'integer'],
            'gathering_id' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['nullable', 'in:scheduled,confirmed,swapped,cancelled'],
            'notes' => ['nullable', 'array'],
            'repeat_frequency' => ['nullable', 'in:weekly,biweekly,monthly'],
            'repeat_until' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
