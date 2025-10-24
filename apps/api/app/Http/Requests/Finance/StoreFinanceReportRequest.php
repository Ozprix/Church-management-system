<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinanceReportRequest extends FormRequest
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
        $types = [
            'donations',
            'pledges',
            'donor-statement',
            'monthly-statement',
            'balance-sheet',
            'donor-letters',
        ];

        return [
            'tenant_id' => ['required', 'integer'],
            'type' => ['required', Rule::in($types)],
            'filters' => ['nullable', 'array'],
            'filters.member_id' => ['required_if:type,donor-statement', 'integer'],
            'filters.from' => ['nullable', 'date'],
            'filters.to' => ['nullable', 'date'],
            'filters.month' => ['nullable', 'date_format:Y-m'],
        ];
    }
}
