<?php

namespace App\Http\Requests\Gathering;

use App\Models\GatheringRegistration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGatheringRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket_type_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(GatheringRegistration::STATUSES)],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'checked_in_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
