<?php

namespace App\Http\Requests\Finance;

use App\Support\PledgeReminderCadence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePledgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fund_id' => ['nullable', 'integer'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'fulfilled_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'frequency' => ['nullable', 'in:one_time,weekly,monthly,quarterly,annually'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:active,paused,fulfilled,cancelled'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'reminder_enabled' => ['sometimes', 'boolean'],
            'reminder_cadence' => [
                'nullable',
                'string',
                Rule::in(PledgeReminderCadence::values()),
                Rule::requiredIf(fn () => $this->boolean('reminder_enabled')),
            ],
            'next_reminder_at' => ['nullable', 'date'],
            'last_reminder_sent_at' => ['nullable', 'date'],
        ];
    }
}
