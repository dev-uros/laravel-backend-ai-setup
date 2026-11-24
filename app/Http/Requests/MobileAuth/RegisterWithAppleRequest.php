<?php

namespace App\Http\Requests\MobileAuth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * No `email`: the address -- possibly a hidden `privaterelay.appleid.com`
 * one -- is read out of the token. Apple hands the phone the person's name
 * only the first time, so the app pre-fills `first_name`/`last_name` from
 * `credential.fullName` on the registration form.
 */
class RegisterWithAppleRequest extends FormRequest
{
    use NormalizesMobileIdentity;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string', 'max:8192'],
            'nonce' => ['required', 'string', 'min:16', 'max:255'],
            'authorization_code' => ['nullable', 'string', 'max:2048'],
            ...MobileUserRules::registrationProfile(),
            ...MobileUserRules::optionalDevice(),
        ];
    }
}
