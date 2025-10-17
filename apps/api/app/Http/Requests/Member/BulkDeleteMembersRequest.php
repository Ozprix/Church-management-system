<?php

namespace App\Http\Requests\Member;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDeleteMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        $existsRule = Rule::exists('members', 'uuid');

        if ($tenantId) {
            $existsRule = $existsRule->where('tenant_id', $tenantId);
        }

        return [
            'member_ids' => ['required', 'array', 'min:1', 'max:200'],
            'member_ids.*' => ['string', 'uuid', $existsRule],
        ];
    }

    protected function tenantId(): ?int
    {
        $tenant = $this->attributes->get('tenant');

        return $tenant instanceof Tenant ? $tenant->id : null;
    }
}
