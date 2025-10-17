<?php

namespace App\Http\Requests\Family;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFamilyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'family_name' => ['sometimes', 'string', 'max:150'],
            'photo_path' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'address' => ['nullable', 'array'],
            'address.line1' => ['nullable', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:120'],
            'address.state' => ['nullable', 'string', 'max:120'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'address.country' => ['nullable', 'string', 'max:2'],

            'members' => ['sometimes', 'array'],
            'members.*.member_id' => ['required', 'integer'],
            'members.*.relationship' => ['nullable', 'string', 'max:50'],
            'members.*.is_primary_contact' => ['sometimes', 'boolean'],
            'members.*.is_emergency_contact' => ['sometimes', 'boolean'],
        ];
    }
}
