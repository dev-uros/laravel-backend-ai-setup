<?php

namespace App\Http\Requests\MobileAuth;

use Illuminate\Foundation\Http\FormRequest;

class SignInWithGoogleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** The `idToken` Google Sign-In returned, issued with our web client ID. */
            'id_token' => ['required', 'string', 'max:8192'],
            ...MobileUserRules::optionalDevice(),
        ];
    }
}
