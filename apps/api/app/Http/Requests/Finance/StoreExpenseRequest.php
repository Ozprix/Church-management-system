<?php

namespace App\Http\Requests\Finance;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', Rule::in([Expense::STATUS_DRAFT])],
            'incurred_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.amount' => ['required', 'numeric', 'min:0.01'],
            'line_items.*.category' => ['nullable', 'string', 'max:100'],
            'line_items.*.metadata' => ['nullable', 'array'],
        ];
    }
}
