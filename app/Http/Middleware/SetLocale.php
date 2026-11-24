<?php

namespace App\Http\Middleware;

use App\Enums\AppLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers the external mobile app in the language it asks for.
 *
 * The app sends `Accept-Language` on every request -- `sr` or `en`, whatever
 * the person picked in it. Anything else, or nothing, is Serbian. This sets the
 * language of the answer only: the language mail and pushes go out in is the
 * one stored on the account (`mobile_users.locale`), which signing in and
 * `PATCH /me` keep up to date.
 *
 * Only on the mobile routes. The internal API keeps its own Serbian messages
 * and English validation, untouched by this.
 */
class SetLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->localeOf($request)->value);

        return $next($request);
    }

    /**
     * The first language in the header this API speaks. Symfony's matcher also
     * takes `sr-Latn-RS` for `sr` and `en-GB` for `en`; with no match it falls
     * back to the first of the list, which is the default.
     */
    private function localeOf(Request $request): AppLocale
    {
        $supported = array_values(array_unique([
            AppLocale::DEFAULT->value,
            ...array_column(AppLocale::cases(), 'value'),
        ]));

        if (! $request->headers->has('Accept-Language')) {
            return AppLocale::DEFAULT;
        }

        return AppLocale::tryFrom((string) $request->getPreferredLanguage($supported)) ?? AppLocale::DEFAULT;
    }
}
