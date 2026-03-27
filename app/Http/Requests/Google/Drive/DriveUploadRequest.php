<?php

namespace App\Http\Requests\Google\Drive;

use Illuminate\Foundation\Http\FormRequest;

class DriveUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
            'file_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
