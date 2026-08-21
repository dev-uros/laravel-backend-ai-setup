<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Thrown when a user the caller asked for does not exist.
 *
 * Extending Symfony's 404 lets Laravel render this through its own exception
 * handling, so the response keeps the framework's native error shape while
 * carrying a message written for a person rather than the default
 * "No query results for model [App\Models\User] 5".
 */
class UserNotFoundException extends NotFoundHttpException
{
    public function __construct()
    {
        parent::__construct('Korisnik nije pronađen.');
    }
}
