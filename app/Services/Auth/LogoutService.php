<?php

namespace App\Services\Auth;

use Laravel\Sanctum\PersonalAccessToken;

class LogoutService
{
    /**
     * Revoke the token a request arrived with.
     *
     * Only that one. The same account may be signed in from a phone and a
     * laptop, and each session holds its own token -- dropping them all would
     * sign the user out everywhere they did not ask to leave. Wholesale
     * revocation belongs to the password reset flow, where losing every old
     * session is the point.
     */
    public function handle(PersonalAccessToken $token): void
    {
        $token->delete();
    }
}
