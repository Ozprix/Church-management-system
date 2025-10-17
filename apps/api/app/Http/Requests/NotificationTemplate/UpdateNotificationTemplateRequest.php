<?php

namespace App\Http\Requests\NotificationTemplate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = optional($this->attributes->get('tenant'))->id;
        $template = $this->route('notification_template');

        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160', Rule::unique('notification_templates', 'slug')->ignore($template?->id)->where('tenant_id', $tenantId)],
            'channel' => ['sometimes', Rule::in(['sms', 'email'])],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'placeholders' => ['nullable', 'array'],
            'placeholders.*' => ['string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
