<?php

namespace App\Http\Requests\Volunteer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVolunteerAvailabilityRequest extends FormRequest
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
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['string'],
            'time_blocks' => ['nullable', 'array'],
            'time_blocks.*.start' => ['required_with:time_blocks', 'string'],
            'time_blocks.*.end' => ['required_with:time_blocks', 'string'],
            'unavailable_from' => ['nullable', 'date'],
            'unavailable_until' => ['nullable', 'date', 'after_or_equal:unavailable_from'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
