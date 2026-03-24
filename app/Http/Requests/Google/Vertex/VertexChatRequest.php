<?php

namespace App\Http\Requests\Google\Vertex;

use Illuminate\Foundation\Http\FormRequest;

class VertexChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'messages' => ['nullable', 'array', 'max:20'],
            'messages.*.role' => ['required_with:messages', 'string', 'in:user,model'],
            'messages.*.content' => ['required_with:messages', 'string', 'max:4000'],
        ];
    }
}
