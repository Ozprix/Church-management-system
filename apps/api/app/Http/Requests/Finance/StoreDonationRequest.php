<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreDonationRequest extends FormRequest
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
            'payment_method_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'in:pending,succeeded,refunded,failed'],
            'received_at' => ['nullable', 'date'],
            'provider' => ['nullable', 'string', 'max:120'],
            'provider_reference' => ['nullable', 'string', 'max:191'],
            'receipt_number' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'items' => ['nullable', 'array'],
            'items.*.fund_id' => ['nullable', 'integer'],
            'items.*.amount' => ['required_with:items', 'numeric', 'min:0.01'],
        ];
    }
}
