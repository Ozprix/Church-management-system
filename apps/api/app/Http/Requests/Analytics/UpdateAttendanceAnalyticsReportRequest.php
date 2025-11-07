<?php

namespace App\Http\Requests\Analytics;

use App\Models\AttendanceAnalyticsReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceAnalyticsReportRequest extends FormRequest
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
            'frequency' => ['sometimes', 'string', Rule::in(AttendanceAnalyticsReport::FREQUENCIES)],
            'channel' => ['sometimes', 'string', Rule::in(AttendanceAnalyticsReport::CHANNELS)],
            'email_recipient' => ['nullable', 'email'],
        ];
    }
}
