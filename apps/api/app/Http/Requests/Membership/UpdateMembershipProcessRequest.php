<?php

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMembershipProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('membership_processes.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'auto_start_on_member_create' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'stages' => ['nullable', 'array'],
            'stages.*.id' => ['nullable', 'integer', 'exists:membership_process_stages,id'],
            'stages.*.key' => ['nullable', 'string', 'max:120'],
            'stages.*.name' => ['required_with:stages', 'string', 'max:255'],
            'stages.*.entry_actions' => ['nullable', 'array'],
            'stages.*.exit_actions' => ['nullable', 'array'],
            'stages.*.reminder_minutes' => ['nullable', 'integer', 'min:1'],
            'stages.*.reminder_template_id' => ['nullable', 'integer', 'exists:notification_templates,id'],
            'stages.*.metadata' => ['nullable', 'array'],
        ];
    }
}
