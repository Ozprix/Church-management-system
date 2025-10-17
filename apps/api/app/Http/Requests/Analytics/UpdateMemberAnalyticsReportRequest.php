<?php

namespace App\Http\Requests\Analytics;

use App\Models\MemberAnalyticsReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberAnalyticsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'filters' => ['nullable', 'array'],
            'frequency' => ['sometimes', 'string', Rule::in(MemberAnalyticsReport::FREQUENCIES)],
            'channel' => ['sometimes', 'string', Rule::in(MemberAnalyticsReport::CHANNELS)],
            'email_recipient' => ['nullable', 'email'],
        ];
    }
}
