<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('notifications.rules_manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'trigger_type' => ['required', 'string', 'max:120'],
            'trigger_config' => ['nullable', 'array'],
            'channel' => ['required', 'string', 'in:sms,email'],
            'notification_template_id' => ['nullable', 'integer', 'exists:notification_templates,id'],
            'delivery_config' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:inactive,active'],
            'throttle_minutes' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
