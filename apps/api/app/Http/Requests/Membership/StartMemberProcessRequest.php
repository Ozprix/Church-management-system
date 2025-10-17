<?php

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;

class StartMemberProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('membership_processes.run') ?? false;
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:members,id'],
        ];
    }
}
