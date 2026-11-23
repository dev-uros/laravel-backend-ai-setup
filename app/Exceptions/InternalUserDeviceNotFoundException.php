<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The calling session has no phone behind it: it came from the web app, or
 * from a version of the mobile app that did not send its device yet.
 */
class InternalUserDeviceNotFoundException extends NotFoundHttpException
{
    public function __construct()
    {
        parent::__construct('Ovaj uređaj nije registrovan.');
    }
}
