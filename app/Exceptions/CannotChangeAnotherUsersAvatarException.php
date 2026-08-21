<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Thrown when a plain user tries to set or remove somebody else's avatar.
 *
 * The avatar endpoints are the only ones a user without the administrator role
 * may write to, and the only thing they may write is their own. Which row the
 * caller is allowed to touch depends on the row, so UserPolicy cannot answer it
 * -- the routes carry a plain id, not a bound model. The check lives in the
 * services instead, with the caller passed in.
 */
class CannotChangeAnotherUsersAvatarException extends AccessDeniedHttpException
{
    public function __construct()
    {
        parent::__construct('Možete menjati samo sopstvenu profilnu sliku.');
    }
}
