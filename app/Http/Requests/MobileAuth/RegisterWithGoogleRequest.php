<?php

namespace App\Http\Requests\MobileAuth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * No `email`: the address is read out of the token.
 */
class RegisterWithGoogleRequest extends FormRequest
{
    use NormalizesMobileIdentity;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** The `idToken` Google Sign-In returned -- the same one a 404 from sign-in came back for is fine. */
            'id_token' => ['required', 'string', 'max:8192'],
            ...MobileUserRules::registrationProfile(),
            ...MobileUserRules::optionalDevice(),
        ];
    }
}
