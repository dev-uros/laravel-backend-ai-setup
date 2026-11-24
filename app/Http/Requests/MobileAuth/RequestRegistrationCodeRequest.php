<?php

namespace App\Http\Requests\MobileAuth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Asking for a code to register with. The address and the handle are checked
 * here, before anything is mailed, so the registration form can say at once
 * that either is taken.
 */
class RequestRegistrationCodeRequest extends FormRequest
{
    use NormalizesMobileIdentity;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => MobileUserRules::freeEmail(),
            /** Checked now so the form can say it is taken; sent again with the code. */
            'username' => MobileUserRules::username(),
        ];
    }
}
