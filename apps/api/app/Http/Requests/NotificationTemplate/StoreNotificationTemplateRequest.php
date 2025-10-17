<?php

namespace App\Http\Requests\NotificationTemplate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160', Rule::unique('notification_templates', 'slug')->where('tenant_id', $tenantId)],
            'channel' => ['required', Rule::in(['sms', 'email'])],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'placeholders' => ['nullable', 'array'],
            'placeholders.*' => ['string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
