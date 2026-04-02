<?php

namespace App\Http\Requests\Google\CloudStorage;

use Illuminate\Foundation\Http\FormRequest;

class CloudStorageListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'directory' => ['nullable', 'string', 'max:255'],
            'recursive' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
