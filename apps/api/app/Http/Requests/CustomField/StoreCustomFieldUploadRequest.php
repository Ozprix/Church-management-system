<?php

namespace App\Http\Requests\CustomField;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomFieldUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $field = $this->route('memberCustomField');

        return $field && in_array($field->data_type, ['file', 'signature'], true);
    }

    public function rules(): array
    {
        $field = $this->route('memberCustomField');
        $config = $field?->config ?? [];

        $rules = [
            'file' => ['required', 'file'],
        ];

        if (!empty($config['max_size'])) {
            // Laravel's max rule expects kilobytes.
            $maxKilobytes = (int) $config['max_size'];
            if ($maxKilobytes > 0) {
                $rules['file'][] = 'max:' . $maxKilobytes;
            }
        }

        if (!empty($config['allowed_mimetypes']) && is_array($config['allowed_mimetypes'])) {
            $rules['file'][] = 'mimetypes:' . implode(',', $config['allowed_mimetypes']);
        }

        if (!empty($config['allowed_extensions']) && is_array($config['allowed_extensions'])) {
            $rules['file'][] = 'mimes:' . implode(',', $config['allowed_extensions']);
        }

        return $rules;
    }
}
