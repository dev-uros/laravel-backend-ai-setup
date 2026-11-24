<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * The phone is registered but holds no push token.
 */
class MobilePushNotificationsDisabledException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct(__('mobile.device.push_disabled'));
    }
}
