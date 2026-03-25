<?php

namespace App\Http\Requests\Google\Vertex;

use Illuminate\Foundation\Http\FormRequest;

class VertexImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'image' => ['required', 'file', 'image', 'max:10240'],
        ];
    }
}
