<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'in:card,bank,mobile_money,cash,other'],
            'brand' => ['nullable', 'string', 'max:120'],
            'last_four' => ['nullable', 'string', 'max:4'],
            'provider' => ['nullable', 'string', 'max:120'],
            'provider_reference' => ['nullable', 'string', 'max:191'],
            'expires_at' => ['nullable', 'date'],
            'is_default' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
