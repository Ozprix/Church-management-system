<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = optional($this->attributes->get('tenant'))->id;

        return [
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => [
                'integer',
                Rule::exists('members', 'id')->where('tenant_id', $tenantId),
            ],
            'status' => ['nullable', Rule::in(['present', 'absent', 'excused'])],
        ];
    }
}
