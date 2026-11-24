<?php

namespace App\Http\Requests\MobileAuth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The code and everything the account is made of, in one request -- the
 * account does not exist until the code comes back, so nothing is kept
 * between the two steps. Multipart when it carries a picture.
 */
class RegisterWithCodeRequest extends FormRequest
{
    use NormalizesMobileIdentity;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => MobileUserRules::freeEmail(),
            /** The six digits from the e-mail. */
            'code' => ['required', 'string', 'max:16'],
            ...MobileUserRules::registrationProfile(),
            ...MobileUserRules::optionalDevice(),
        ];
    }
}
