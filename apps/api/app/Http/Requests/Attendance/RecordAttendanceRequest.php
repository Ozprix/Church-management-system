<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = optional($this->attributes->get('tenant'))->id;

        $memberRule = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'member_id' => [
                $memberRule,
                'integer',
                Rule::exists('members', 'id')->where('tenant_id', $tenantId),
            ],
            'status' => ['nullable', Rule::in(['present', 'absent', 'excused'])],
            'check_in_method' => ['nullable', 'string', 'max:50'],
            'checked_in_at' => ['nullable', 'date'],
            'checked_out_at' => ['nullable', 'date', 'after_or_equal:checked_in_at'],
            'notes' => ['nullable', 'array'],
        ];
    }
}
