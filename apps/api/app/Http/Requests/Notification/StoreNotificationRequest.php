<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationRequest extends FormRequest
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
            'notification_template_id' => ['nullable', 'integer', Rule::exists('notification_templates', 'id')->where('tenant_id', $tenantId)],
            'member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->where('tenant_id', $tenantId)],
            'channel' => ['required_without:notification_template_id', Rule::in(['sms', 'email'])],
            'recipient' => ['required_without:member_id', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
            'scheduled_for' => ['nullable', 'date'],
        ];
    }
}
