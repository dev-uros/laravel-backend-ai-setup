<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Thrown when an administrator tries to change their own role.
 *
 * The only self-change available to an administrator is a demotion, and it is
 * refused for two reasons: it is almost always a misclick, and it is the one
 * change nobody can undo for themselves afterwards. Someone else with the role
 * has to do it, which also guarantees a second administrator exists at the
 * moment the last one is demoted.
 */
class CannotChangeOwnRoleException extends AccessDeniedHttpException
{
    public function __construct()
    {
        parent::__construct('Ne možete promeniti sopstvenu ulogu. Zamolite drugog administratora.');
    }
}
