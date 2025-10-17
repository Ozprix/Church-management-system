<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $notification = $this->route('notification');
        $tenantId = optional($this->attributes->get('tenant'))->id;

        return [
            'status' => ['sometimes', Rule::in(['queued', 'failed'])],
            'scheduled_for' => ['nullable', 'date'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
            'notification_template_id' => ['nullable', 'integer', Rule::exists('notification_templates', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
