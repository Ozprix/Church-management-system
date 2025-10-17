<?php

namespace App\Http\Requests\Analytics;

use App\Models\MemberAnalyticsReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberAnalyticsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($tenant = $this->attributes->get('tenant')) {
            $this->merge([
                'tenant_id' => $tenant->id,
                'user_id' => optional($this->user())->id,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'filters' => ['nullable', 'array'],
            'frequency' => ['required', 'string', Rule::in(MemberAnalyticsReport::FREQUENCIES)],
            'channel' => ['required', 'string', Rule::in(MemberAnalyticsReport::CHANNELS)],
            'email_recipient' => ['nullable', 'email'],
        ];
    }
}
