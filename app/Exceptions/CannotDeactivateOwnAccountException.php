<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Thrown when an administrator tries to switch off their own account.
 *
 * Switching yourself off is the one action you cannot undo yourself, and it is
 * what would let the last administrator lock everybody out of the panel.
 *
 * Refusing it is also the whole guarantee that an active administrator always
 * remains: only an active administrator can reach this endpoint, and they are
 * still active once somebody else has been switched off. The count can never
 * reach zero, which is why there is no separate "last administrator" check.
 */
class CannotDeactivateOwnAccountException extends AccessDeniedHttpException
{
    public function __construct()
    {
        parent::__construct('Ne možete isključiti sopstveni nalog. Zamolite drugog administratora.');
    }
}
