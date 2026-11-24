<?php

namespace App\Http\Requests\MobileAuth;

use Illuminate\Foundation\Http\FormRequest;

class SignInWithAppleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** `credential.identityToken`. */
            'id_token' => ['required', 'string', 'max:8192'],
            /** The raw nonce the app generated; Apple was given its SHA-256. */
            'nonce' => ['required', 'string', 'min:16', 'max:255'],
            /**
             * `credential.authorizationCode`, traded for the refresh token that
             * lets a deleted account's Apple authorisation be revoked. Spent
             * only when the sign-in succeeds, so after a 404 the same one goes
             * to registration.
             */
            'authorization_code' => ['nullable', 'string', 'max:2048'],
            ...MobileUserRules::optionalDevice(),
        ];
    }
}
