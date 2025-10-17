<?php

namespace App\Http\Requests\Volunteer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVolunteerAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'volunteer_role_id' => ['nullable', 'integer'],
            'volunteer_team_id' => ['nullable', 'integer'],
            'gathering_id' => ['nullable', 'integer'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['nullable', 'in:scheduled,confirmed,swapped,cancelled'],
            'notes' => ['nullable', 'array'],
            'repeat_frequency' => ['nullable', 'in:weekly,biweekly,monthly'],
            'repeat_until' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
