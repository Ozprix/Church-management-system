<?php

namespace App\Http\Requests\Gathering;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGatheringRequest extends FormRequest
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
            'service_id' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['scheduled', 'in_progress', 'completed', 'cancelled'])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
