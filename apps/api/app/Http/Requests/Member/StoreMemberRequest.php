<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'preferred_name' => ['nullable', 'string', 'max:120'],
            'gender' => ['nullable', 'in:male,female,non_binary,unspecified'],
            'dob' => ['nullable', 'date'],
            'marital_status' => ['nullable', 'in:single,married,divorced,widowed,separated,partnered'],
            'membership_status' => ['nullable', 'in:prospect,active,inactive,visitor,suspended,transferred'],
            'membership_stage' => ['nullable', 'string', 'max:150'],
            'joined_at' => ['nullable', 'date'],
            'photo_path' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],

            'contacts' => ['sometimes', 'array'],
            'contacts.*.id' => ['sometimes', 'integer'],
            'contacts.*.type' => ['required_with:contacts', 'in:email,mobile,home_phone,address,social,other'],
            'contacts.*.label' => ['nullable', 'string', 'max:120'],
            'contacts.*.value' => ['required_with:contacts', 'string', 'max:512'],
            'contacts.*.is_primary' => ['sometimes', 'boolean'],
            'contacts.*.is_emergency' => ['sometimes', 'boolean'],
            'contacts.*.communication_preference' => ['nullable', 'in:email,sms,call,mail'],

            'families' => ['sometimes', 'array'],
            'families.*.family_id' => ['required', 'integer'],
            'families.*.relationship' => ['nullable', 'string', 'max:50'],
            'families.*.is_primary_contact' => ['sometimes', 'boolean'],
            'families.*.is_emergency_contact' => ['sometimes', 'boolean'],

            'custom_values' => ['sometimes', 'array'],
            'custom_values.*.field_id' => ['required', 'integer'],
            'custom_values.*.value' => ['nullable'],
        ];
    }
}
