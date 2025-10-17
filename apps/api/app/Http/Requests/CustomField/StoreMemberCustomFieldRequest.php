<?php

namespace App\Http\Requests\CustomField;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberCustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150'],
            'data_type' => ['required', 'in:text,number,date,boolean,select,multi_select,file,signature'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'config' => ['nullable', 'array'],
        ];

        if (in_array($this->input('data_type'), ['file', 'signature'], true)) {
            $rules['config.allowed_extensions'] = ['nullable', 'array'];
            $rules['config.allowed_extensions.*'] = ['string'];
            $rules['config.allowed_mimetypes'] = ['nullable', 'array'];
            $rules['config.allowed_mimetypes.*'] = ['string'];
            $rules['config.max_size'] = ['nullable', 'integer', 'min:1'];
        }

        return $rules;
    }
}
