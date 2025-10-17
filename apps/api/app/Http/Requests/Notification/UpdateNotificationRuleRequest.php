<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('notifications.rules_manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'trigger_type' => ['sometimes', 'string', 'max:120'],
            'trigger_config' => ['nullable', 'array'],
            'channel' => ['sometimes', 'string', 'in:sms,email'],
            'notification_template_id' => ['nullable', 'integer', 'exists:notification_templates,id'],
            'delivery_config' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:inactive,active'],
            'throttle_minutes' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
