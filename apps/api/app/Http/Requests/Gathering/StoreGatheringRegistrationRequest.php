<?php

namespace App\Http\Requests\Gathering;

use App\Models\GatheringRegistration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGatheringRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket_type_id' => ['nullable', 'integer'],
            'member_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(GatheringRegistration::STATUSES)],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'name' => ['required_without:member_id', 'string', 'max:150'],
            'email' => ['required_without:member_id', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'checked_in_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
