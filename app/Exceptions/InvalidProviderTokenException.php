<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * A Google or Apple token that cannot be trusted -- wrong signature, issuer,
 * audience or nonce, expired, or without a verified address. Why goes to the
 * log only.
 */
class InvalidProviderTokenException extends UnprocessableEntityHttpException
{
    public function __construct(string $provider)
    {
        parent::__construct(__('mobile.auth.invalid_provider_token', ['provider' => $provider]));
    }
}
