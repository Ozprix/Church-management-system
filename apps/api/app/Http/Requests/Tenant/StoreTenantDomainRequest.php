<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tenancy.manage_onboarding') ?? false;
    }

    public function rules(): array
    {
        return [
            'hostname' => ['required', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
