<?php

namespace App\Support\Apple;

/**
 * What Apple answers an authorization code with, as far as we keep it.
 *
 * `sub` comes from the identity token in that same answer and says whose
 * authorisation the refresh token is, so it can be held against the Apple ID
 * the phone signed in with.
 */
final readonly class AppleRefreshTokenData
{
    public function __construct(
        public string $refreshToken,
        public string $sub,
    ) {}
}
