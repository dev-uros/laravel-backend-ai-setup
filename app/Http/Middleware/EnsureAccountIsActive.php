<?php

namespace App\Http\Middleware;

use App\Exceptions\AccountDeactivatedException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops a deactivated account from using the API.
 *
 * Deactivation already revokes every token the account holds, so in the normal
 * course of events nothing gets this far. This is the second lock: it catches a
 * token minted before the switch-off, or by a code path that forgets to revoke.
 * The check reads the database column on every request, so switching an account
 * off takes effect on the next one.
 *
 * Runs after `auth:sanctum` -- it relies on the guard having resolved a user.
 */
class EnsureAccountIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isActive() === false) {
            throw new AccountDeactivatedException;
        }

        return $next($request);
    }
}
