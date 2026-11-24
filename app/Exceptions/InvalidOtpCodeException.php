<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * A code that is wrong, expired, used up, or was never issued -- one answer for
 * all of them, so a guesser learns nothing from which it was.
 */
class InvalidOtpCodeException extends UnprocessableEntityHttpException
{
    public function __construct()
    {
        parent::__construct(__('mobile.auth.invalid_code'));
    }
}
