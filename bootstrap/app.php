<?php

use App\Http\Middleware\LogRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * In production every request arrives through Traefik and then through
         * the application's own nginx, so the socket peer is always a container
         * address. Without this, `$request->ip()` is that address -- and it is
         * the value `user_logs`, `login_attempts` and the request log all
         * record, which would make every row say the same thing about a
         * different person. `url()` would also emit http:// links behind an
         * https site.
         *
         * The **private ranges**, not `at: '*'`. Docker hands the proxy's
         * address out afresh whenever the network is recreated, so a single
         * pinned address would silently stop matching -- but every address it
         * can hand out is private, so the ranges cover it without ever going
         * stale.
         *
         * `'*'` looks safe here -- nothing but the proxy can reach PHP -- but it
         * also declares the outside world a trusted proxy, and that is what
         * breaks Horizon and Pulse: Laravel Sentinel sits in front of both and
         * refuses a `local` request that has a public address **and** claims to
         * arrive through a proxy we trust, which with `'*'` is every request.
         */
        $middleware->trustProxies(
            at: ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', 'fc00::/7', '::1'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        /**
         * One JSON line per finished request, on both stacks. Appended, so it
         * wraps everything after it and sees the response the client actually
         * got -- including one rendered from an exception.
         */
        $middleware->append(LogRequests::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
