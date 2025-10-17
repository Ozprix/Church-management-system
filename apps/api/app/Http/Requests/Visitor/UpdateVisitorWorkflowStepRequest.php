<?php

namespace App\Http\Requests\Visitor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisitorWorkflowStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'step_number' => ['sometimes', 'integer', 'min:1'],
            'name' => ['nullable', 'string', 'max:191'],
            'delay_minutes' => ['nullable', 'integer', 'min:0'],
            'channel' => ['sometimes', 'in:email,sms,task'],
            'notification_template_id' => ['nullable', 'integer', 'exists:notification_templates,id'],
            'metadata' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
