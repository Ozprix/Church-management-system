<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantSecurityPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $roleRule = Rule::exists('roles', 'slug');

        if ($tenantId !== null) {
            $roleRule = $roleRule->where('tenant_id', $tenantId);
        }

        return [
            'enforce_two_factor' => ['sometimes', 'boolean'],
            'enforced_role_slugs' => ['sometimes', 'array'],
            'enforced_role_slugs.*' => [
                'string',
                $roleRule,
            ],
            'enforced_permission_slugs' => ['sometimes', 'array'],
            'enforced_permission_slugs.*' => [
                'string',
                Rule::exists('permissions', 'slug'),
            ],
        ];
    }
}
