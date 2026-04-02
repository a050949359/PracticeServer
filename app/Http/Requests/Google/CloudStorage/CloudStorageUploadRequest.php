<?php

namespace App\Http\Requests\Google\CloudStorage;

use Illuminate\Foundation\Http\FormRequest;

class CloudStorageUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
            'directory' => ['nullable', 'string', 'max:255'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'visibility' => ['nullable', 'in:public,private'],
        ];
    }
}
