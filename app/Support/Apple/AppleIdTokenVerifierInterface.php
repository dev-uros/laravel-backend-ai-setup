<?php

namespace App\Support\Apple;

use App\Exceptions\InvalidProviderTokenException;
use App\Exceptions\ProviderSignInUnavailableException;

interface AppleIdTokenVerifierInterface
{
    /**
     * Check an identity token from Sign in with Apple and say whose it is.
     *
     * `$nonce` is the raw value the app generated; the token must carry its
     * SHA-256, which the app handed to Apple when it asked for the token.
     *
     * @throws InvalidProviderTokenException when the token cannot be trusted, for any reason
     * @throws ProviderSignInUnavailableException when no bundle ID is configured, or Apple's keys cannot be fetched
     */
    public function verify(string $idToken, string $nonce): AppleIdentityData;
}
