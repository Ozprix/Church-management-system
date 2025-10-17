<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tenancy.manage_onboarding') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:tenants,slug'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'active', 'suspended'])],
            'plan_code' => ['nullable', 'string', 'max:120'],
            'plan_options.status' => ['nullable', 'string', Rule::in(['trial', 'active', 'canceled'])],
            'plan_options.trial_ends_at' => ['nullable', 'date'],
            'plan_options.renews_at' => ['nullable', 'date'],
            'plan_options.seat_limit' => ['nullable', 'integer', 'min:1'],
            'plan_options.metadata' => ['nullable', 'array'],
            'domains' => ['nullable', 'array'],
            'domains.*.hostname' => ['required_with:domains', 'string', 'max:255'],
            'domains.*.is_primary' => ['nullable', 'boolean'],
            'timezone' => ['nullable', 'string', 'max:120'],
            'locale' => ['nullable', 'string', 'max:5'],
            'meta' => ['nullable', 'array'],
            'admin' => ['nullable', 'array:name,email,password'],
            'admin.name' => ['required_with:admin', 'string', 'max:255'],
            'admin.email' => ['required_with:admin', 'string', 'email', 'max:255'],
            'admin.password' => ['required_with:admin', 'string', 'min:8'],
        ];
    }
}
