<?php

namespace App\Http\Requests\MobileUser;

use Illuminate\Foundation\Http\FormRequest;

class DeleteMobileUserRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /**
             * For an account linked to Apple: a fresh `authorizationCode` from
             * Sign in with Apple on the deletion screen, so its refresh token
             * can be revoked. Optional -- the deletion goes ahead without it,
             * revoking the token kept at sign-in if there is one.
             */
            'apple_authorization_code' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
