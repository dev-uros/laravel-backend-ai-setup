<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Thrown when an email and password pair does not identify a user.
 *
 * 401 rather than 422: the request was well formed, the credentials were simply
 * wrong. Reserving 422 for shape problems keeps "this field is malformed" and
 * "these details are incorrect" distinguishable by status alone.
 *
 * The message never says which half was wrong -- that would let an attacker
 * enumerate which email addresses have accounts.
 */
class InvalidCredentialsException extends UnauthorizedHttpException
{
    public function __construct()
    {
        parent::__construct('Bearer', 'Neispravni podaci za prijavu.');
    }
}
