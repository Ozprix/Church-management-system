<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'tenant_id' => ['required', 'integer'],
            'type' => ['required', 'in:donations,pledges,donor-statement'],
            'filters' => ['nullable', 'array'],
            'filters.member_id' => ['required_if:type,donor-statement', 'integer'],
            'filters.from' => ['nullable', 'date'],
            'filters.to' => ['nullable', 'date'],
        ];
    }
}
