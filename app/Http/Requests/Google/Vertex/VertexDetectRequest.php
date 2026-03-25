<?php

namespace App\Http\Requests\Google\Vertex;

use Illuminate\Foundation\Http\FormRequest;

class VertexDetectRequest extends FormRequest
{
    private const ALLOWED_FEATURE_TYPES = [
        'TEXT_DETECTION',
        'DOCUMENT_TEXT_DETECTION',
        'LABEL_DETECTION',
        'OBJECT_LOCALIZATION',
        'FACE_DETECTION',
        'LOGO_DETECTION',
        'LANDMARK_DETECTION',
        'SAFE_SEARCH_DETECTION',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'max:10240'],
            'types' => ['nullable', 'array', 'min:1', 'max:8'],
            'types.*' => ['required_with:types', 'string', 'in:'.implode(',', self::ALLOWED_FEATURE_TYPES)],
        ];
    }
}
