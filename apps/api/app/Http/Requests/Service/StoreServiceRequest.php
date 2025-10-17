<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
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
        $tenantId = $this->tenantId();

        return [
            'tenant_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', Rule::unique('services', 'slug')->where('tenant_id', $tenantId)],
            'short_code' => ['nullable', 'string', 'max:20', Rule::unique('services', 'short_code')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string'],
            'default_location' => ['nullable', 'string', 'max:255'],
            'default_start_time' => ['nullable', 'date_format:H:i'],
            'default_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'absence_threshold' => ['nullable', 'integer', 'min:1', 'max:10'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    private function tenantId(): ?int
    {
        return optional($this->attributes->get('tenant'))->id;
    }
}
