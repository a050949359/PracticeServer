<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class PracticeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $methodName = $this->route()?->getActionMethod();

        return match ($methodName) {
            'echoText' => [
                'text' => ['required', 'string', 'max:100'],
            ],
            'sumValues' => [
                'a' => ['required', 'numeric'],
                'b' => ['required', 'numeric'],
            ],
            default => [],
        };
    }
}
