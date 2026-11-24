<?php

namespace App\Http\Requests\MobileAuth;

use Illuminate\Foundation\Http\FormRequest;

class SignInWithCodeRequest extends FormRequest
{
    use NormalizesMobileIdentity;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => MobileUserRules::email(),
            /** The six digits from the e-mail. */
            'code' => ['required', 'string', 'max:16'],
            ...MobileUserRules::optionalDevice(),
        ];
    }
}
