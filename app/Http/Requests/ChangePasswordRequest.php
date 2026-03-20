<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ChangePasswordRequest extends FormRequest
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
        $passwordRule = PasswordRule::min((int) config('auth.password_policy.min_length', 12));

        if ((bool) config('auth.password_policy.require_mixed_case', true)) {
            $passwordRule = $passwordRule->mixedCase();
        }

        if ((bool) config('auth.password_policy.require_numbers', true)) {
            $passwordRule = $passwordRule->numbers();
        }

        if ((bool) config('auth.password_policy.require_symbols', true)) {
            $passwordRule = $passwordRule->symbols();
        }

        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', $passwordRule],
        ];
    }
}
