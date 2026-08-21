<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Thrown when an activation link no longer works: the token expired, was already
 * used, or never matched the email it arrived with.
 *
 * 422 rather than 404: the request was well formed and the endpoint exists, the
 * token simply cannot be acted on. The message is deliberately vague about which
 * of the three it was.
 */
class InvalidActivationTokenException extends UnprocessableEntityHttpException
{
    public function __construct()
    {
        parent::__construct('Link za aktivaciju nije važeći ili je istekao. Zatražite novi.');
    }
}
