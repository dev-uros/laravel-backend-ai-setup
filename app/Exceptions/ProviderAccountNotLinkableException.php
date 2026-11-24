<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * The address Google or Apple vouched for has an account here, but the
 * provider may not be linked to it: either it is not authoritative for the
 * address (a Google account opened on a Yahoo address), or the account is
 * already linked to a different Google or Apple identity. The person signs in
 * with an emailed code instead.
 */
class ProviderAccountNotLinkableException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct(__('mobile.auth.not_linkable'));
    }
}
