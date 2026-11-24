<?php

namespace App\Support\Google;

use App\Exceptions\InvalidProviderTokenException;
use App\Exceptions\ProviderSignInUnavailableException;

interface GoogleIdTokenVerifierInterface
{
    /**
     * Check an ID token from Google Sign-In and say whose it is.
     *
     * @throws InvalidProviderTokenException when the token cannot be trusted, for any reason
     * @throws ProviderSignInUnavailableException when there is no client ID, or Google's keys cannot be fetched
     */
    public function verify(string $idToken): GoogleIdentityData;
}
