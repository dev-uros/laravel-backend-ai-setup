<?php

namespace App\Http\Middleware;

use App\Exceptions\WrongAudienceException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only internal users (`users`) reach the internal API.
 *
 * Without this, a token minted by the external app's `MobileUser` would pass
 * `auth:sanctum` on every internal route -- the guard does not care which model
 * a token belongs to. Runs right after `auth:sanctum` and before
 * `EnsureAccountIsActive`, which asks the caller something only `User` answers.
 */
class EnsureInternalUser
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof User) {
            throw WrongAudienceException::internalOnly();
        }

        return $next($request);
    }
}
