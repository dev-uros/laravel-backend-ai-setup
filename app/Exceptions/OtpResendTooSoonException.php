<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Another code was asked for before the last one is a minute old. The wait is
 * in the `Retry-After` header -- the same place the route throttle puts it, so
 * the app reads every 429 the same way and counts down from it.
 */
class OtpResendTooSoonException extends TooManyRequestsHttpException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct($retryAfter, __('mobile.auth.resend_too_soon', ['seconds' => $retryAfter]));
    }
}
