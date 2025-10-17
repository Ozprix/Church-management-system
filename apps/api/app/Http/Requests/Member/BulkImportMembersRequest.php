<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class BulkImportMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeRequest = new StoreMemberRequest();
        $baseRules = $storeRequest->rules();

        $prefixedRules = [];

        foreach ($baseRules as $field => $rules) {
            $prefixedRules["members.*.{$field}"] = $rules;
        }

        return array_merge([
            'members' => ['required', 'array', 'min:1', 'max:50'],
        ], $prefixedRules);
    }
}
