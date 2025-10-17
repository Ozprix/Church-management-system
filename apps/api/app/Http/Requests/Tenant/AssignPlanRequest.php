<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tenancy.manage_plans') ?? false;
    }

    public function rules(): array
    {
        return [
            'plan_code' => ['required', 'string', 'exists:plans,code'],
            'status' => ['nullable', 'string', Rule::in(['trial', 'active', 'canceled'])],
            'trial_ends_at' => ['nullable', 'date'],
            'renews_at' => ['nullable', 'date'],
            'seat_limit' => ['nullable', 'integer', 'min:1'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
