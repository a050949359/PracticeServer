<?php

namespace App\Http\Requests;

use Illuminate\Container\Attributes\Log as AttributesLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
class UserRequest extends FormRequest
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
        Log::info('UserRequest rules method called: ' . $this->route()->getActionMethod());
        return [
            //
        ];
    }
}
