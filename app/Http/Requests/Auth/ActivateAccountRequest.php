<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ActivateAccountRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** The email address the activation link was sent to. */
            'email' => ['required', 'string', 'email'],
            /** The token from the activation link. Valid once, then discarded. */
            'token' => ['required', 'string'],
            /** At least 8 characters, with an upper and lower case letter, a number and a symbol. Must not appear in a known data breach. */
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ];
    }
}
