<?php

namespace App\Http\Requests\Volunteer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVolunteerRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $role = $this->route('volunteer_role');
        $tenantId = optional($this->attributes->get('tenant'))->id;

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('volunteer_roles', 'slug')->ignore($role?->id)->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string'],
            'skills_required' => ['nullable', 'array'],
            'skills_required.*' => ['string', 'max:120'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer'],
        ];
    }
}
