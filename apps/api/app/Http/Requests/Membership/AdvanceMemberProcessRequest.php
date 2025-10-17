<?php

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;

class AdvanceMemberProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('membership_processes.run') ?? false;
    }

    public function rules(): array
    {
        return [
            'next_stage_id' => ['nullable', 'integer', 'exists:membership_process_stages,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
