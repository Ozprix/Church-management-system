<?php

namespace App\Http\Requests\Finance\Recurring;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecurringDonationScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => ['nullable', 'integer'],
            'payment_method_id' => ['nullable', 'integer'],
            'frequency' => ['sometimes', 'in:weekly,biweekly,monthly,quarterly,annually'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', 'in:active,paused,cancelled'],
            'starts_on' => ['sometimes', 'date'],
            'ends_on' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
