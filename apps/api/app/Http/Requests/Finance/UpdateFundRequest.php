<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fund = $this->route('fund');
        $tenantId = optional($this->attributes->get('tenant'))->id;

        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160', Rule::unique('funds', 'slug')->ignore($fund?->id)->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
