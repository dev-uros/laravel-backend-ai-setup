<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Registering with Google or Apple when the address or the provider account
 * already has an account. Linking belongs to signing in, never to registering.
 */
class MobileAccountAlreadyExistsException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct(__('mobile.auth.account_exists'));
    }
}
