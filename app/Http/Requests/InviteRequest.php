<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteRequest extends FormRequest
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
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'context' => [
                'required',
                'string',
                'in:user_invited_register,staff_invited_register',
            ],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ];
    }

    public function messages(): array
    {
        return [
            'context.in' => '邀請情境不在允許範圍內。',
        ];
    }
}
