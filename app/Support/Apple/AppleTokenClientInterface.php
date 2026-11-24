<?php

namespace App\Support\Apple;

interface AppleTokenClientInterface
{
    /**
     * Trade the phone's authorization code for a refresh token.
     *
     * The code is good for five minutes and only once, so this is asked
     * exactly once and never retried. `$clientId` is the bundle ID the code
     * was issued to.
     *
     * @throws AppleTokenRequestFailedException when Apple refuses, cannot be reached, or no key is configured
     */
    public function exchangeCode(string $authorizationCode, string $clientId): AppleRefreshTokenData;

    /**
     * Tell Apple the refresh token -- and with it the user's authorisation of
     * this application -- is no longer wanted.
     *
     * @throws AppleTokenRequestFailedException when Apple refuses, cannot be reached, or no key is configured
     */
    public function revoke(string $refreshToken, string $clientId): void;
}
