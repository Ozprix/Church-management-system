<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
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
            'member_id' => ['nullable', 'integer'],
            'type' => ['required', 'in:card,bank,mobile_money,cash,other'],
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
