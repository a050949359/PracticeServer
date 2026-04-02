<?php

namespace App\Http\Requests\Google\CloudStorage;

use Illuminate\Foundation\Http\FormRequest;

class CloudStoragePathRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:1024'],
        ];
    }
}
