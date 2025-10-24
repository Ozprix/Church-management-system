<?php

namespace App\Http\Requests\Family;

use Illuminate\Foundation\Http\FormRequest;

class SendFamilyCommunicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', 'in:email,sms'],
            'target' => ['nullable', 'in:primary,emergency,all'],
            'subject' => ['required_if:channel,email', 'nullable', 'string', 'max:150'],
            'body' => ['required', 'string'],
        ];
    }
}
