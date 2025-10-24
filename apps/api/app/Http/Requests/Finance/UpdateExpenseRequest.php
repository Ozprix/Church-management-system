<?php

namespace App\Http\Requests\Finance;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', Rule::in([Expense::STATUS_DRAFT, Expense::STATUS_PENDING])],
            'incurred_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'line_items' => ['nullable', 'array', 'min:1'],
            'line_items.*.description' => ['required_with:line_items', 'string', 'max:255'],
            'line_items.*.amount' => ['required_with:line_items', 'numeric', 'min:0.01'],
            'line_items.*.category' => ['nullable', 'string', 'max:100'],
            'line_items.*.metadata' => ['nullable', 'array'],
        ];
    }
}
