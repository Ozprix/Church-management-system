<?php

namespace App\Http\Requests\Volunteer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVolunteerTeamRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', Rule::unique('volunteer_teams', 'slug')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer'],
        ];
    }
}
