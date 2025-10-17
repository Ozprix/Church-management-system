<?php

namespace App\Http\Requests\Volunteer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVolunteerRoleRequest extends FormRequest
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
        $tenantId = optional($this->attributes->get('tenant'))->id;

        return [
            'tenant_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('volunteer_roles', 'slug')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string'],
            'skills_required' => ['nullable', 'array'],
            'skills_required.*' => ['string', 'max:120'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer'],
        ];
    }
}
