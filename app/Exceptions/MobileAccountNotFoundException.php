<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Google or Apple vouched for somebody who has no account here. The app takes
 * the 404 to the registration screen, with the address from the token.
 */
class MobileAccountNotFoundException extends NotFoundHttpException
{
    public static function forProvider(string $provider): self
    {
        return new self(__('mobile.auth.account_not_found', ['provider' => $provider]));
    }

    /**
     * Asked for by id -- only the admin panel does that (the app's own `me`
     * cannot miss), so in the internal API's language, Serbian.
     */
    public static function account(): self
    {
        return new self('Korisnik aplikacije nije pronađen.');
    }
}
