<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Thrown when the account behind a request has been switched off.
 *
 * 403 rather than 401: the caller proved who they are, and repeating the login
 * would not help them. A 401 invites the SPA to send them back to a form that
 * will refuse them again.
 *
 * Unlike a failed login this message says exactly what is wrong. It is only ever
 * reached once the caller has proved the account is theirs -- either with a
 * valid password or a valid token -- so it tells them nothing they should not
 * already know, and being vague here would just leave them stuck.
 */
class AccountDeactivatedException extends AccessDeniedHttpException
{
    public function __construct()
    {
        parent::__construct('Nalog je isključen. Obratite se administratoru.');
    }
}
