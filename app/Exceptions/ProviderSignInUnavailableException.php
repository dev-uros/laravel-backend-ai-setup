<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Signing in with Google or Apple cannot be checked right now: no client ID is
 * configured (`GOOGLE_OAUTH_CLIENT_IDS`, `APPLE_CLIENT_IDS`), or the provider's
 * keys cannot be fetched.
 */
class ProviderSignInUnavailableException extends ServiceUnavailableHttpException
{
    public function __construct(string $provider)
    {
        parent::__construct(null, __('mobile.auth.provider_unavailable', ['provider' => $provider]));
    }
}
