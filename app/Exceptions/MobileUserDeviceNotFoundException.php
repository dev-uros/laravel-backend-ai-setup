<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The session has no phone registered -- `PUT /mobile/v1/device` was never
 * called with this token.
 */
class MobileUserDeviceNotFoundException extends NotFoundHttpException
{
    public function __construct()
    {
        parent::__construct(__('mobile.device.not_found'));
    }
}
