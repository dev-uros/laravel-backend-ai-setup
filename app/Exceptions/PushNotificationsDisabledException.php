<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * The phone exists, but holds no push token -- notifications were never
 * allowed on it, were switched off, or Expo reported it gone.
 */
class PushNotificationsDisabledException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('Obaveštenja nisu uključena na ovom uređaju.');
    }
}
