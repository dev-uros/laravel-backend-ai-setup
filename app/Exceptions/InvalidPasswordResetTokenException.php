<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Thrown when a password reset link no longer works: the token expired, was
 * already used, or does not belong to the email it arrived with.
 *
 * Deliberately vague about which, for the same reason the login error is.
 */
class InvalidPasswordResetTokenException extends UnprocessableEntityHttpException
{
    public function __construct()
    {
        parent::__construct('Link za promenu lozinke nije važeći ili je istekao. Zatražite novi.');
    }
}
