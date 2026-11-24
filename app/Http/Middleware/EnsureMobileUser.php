<?php

namespace App\Http\Middleware;

use App\Exceptions\WrongAudienceException;
use App\Models\MobileUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The other half of `EnsureInternalUser`: only the external app's users reach
 * its API.
 *
 * It also refuses a blocked account, reading the column on every request
 * rather than trusting the token -- blocking somebody takes effect on their
 * next tap. Blocking deletes the tokens as well; this is the second lock.
 */
class EnsureMobileUser
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof MobileUser) {
            throw WrongAudienceException::mobileOnly();
        }

        if ($user->isBlocked()) {
            throw WrongAudienceException::blocked();
        }

        return $next($request);
    }
}
