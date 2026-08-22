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
         * `at: '*'` rather than a fixed CIDR: the proxy's address is handed out
         * by Docker and changes whenever the network is recreated, and a
         * trusted range that silently stops matching fails the same way as
         * having none. It is safe only because no container in
         * compose.prod.yaml publishes a port -- the sole route to PHP is
         * through the proxy, which overwrites these headers rather than
         * appending to whatever a caller sent.
         */
        $middleware->trustProxies(
            at: '*',
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
