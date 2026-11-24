<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * A token reached a route meant for the other population.
 *
 * Internal users (`users`) and the external app's users (`mobile_users`) mint
 * tokens against the same `auth:sanctum` guard, and Sanctum resolves a token to
 * whichever model minted it. A token is proof of who somebody is, not of which
 * half of the API they may use -- so each route group states its audience and
 * this refuses the other one.
 *
 * 403 rather than 401: the caller proved who they are, and signing in again
 * would not change the answer. Also used for a blocked mobile account, for the
 * same reason.
 */
class WrongAudienceException extends AccessDeniedHttpException
{
    /**
     * In Serbian whatever the app's locale: the internal API speaks only
     * Serbian, and the caller here is an external-app token that strayed.
     */
    public static function internalOnly(): self
    {
        return new self(__('mobile.access.internal_only', [], 'sr'));
    }

    public static function mobileOnly(): self
    {
        return new self(__('mobile.access.mobile_only'));
    }

    public static function blocked(): self
    {
        return new self(__('mobile.access.blocked'));
    }
}
