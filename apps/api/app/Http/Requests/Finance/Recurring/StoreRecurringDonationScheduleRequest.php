<?php

namespace App\Http\Requests\Finance\Recurring;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecurringDonationScheduleRequest extends FormRequest
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
            'frequency' => ['required', 'in:weekly,biweekly,monthly,quarterly,annually'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'in:active,paused,cancelled'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:starts_on'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
