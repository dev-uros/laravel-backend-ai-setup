<?php

namespace App\Http\Middleware;

use App\Logging\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * One structured line per finished request, the way fastify/pino does it.
 *
 * The line is written in terminate(), which runs after the response has gone
 * back to the client, so building and encoding it costs the caller nothing.
 */
class LogRequests
{
    /**
     * The header a proxy or the SPA can use to name the request itself, and the
     * one the response carries back so a person can quote it in a bug report.
     */
    private const HEADER = 'X-Request-Id';

    public function __construct(private readonly RequestContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('log.started_at', microtime(true));

        $requestId = $this->requestId($request);
        $request->attributes->set('log.request_id', $requestId);

        /**
         * Into the context rather than a local variable: from here every Log
         * call made anywhere in this request carries the same request_id --
         * including the stack trace of an uncaught exception, and the logs of
         * any queued job this request dispatches, because Laravel carries the
         * context into the queue.
         */
        Context::add('request_id', $requestId);

        $response = $next($request);

        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    /**
     * Write the line, after the response has been sent.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (! $this->context->shouldLog($request)) {
            return;
        }

        try {
            Log::log(
                $this->context->level($response),
                'request',
                $this->context->build(
                    $request,
                    $response,
                    $request->attributes->get('log.request_id', 'unknown'),
                    $request->attributes->get('log.started_at', microtime(true)),
                ),
            );
        } catch (Throwable $e) {
            /**
             * A logger that can take the application down is worse than no
             * logger. The request is already answered by the time this runs, so
             * the only honest thing left is to say the line was lost.
             */
            Log::warning('request log failed', ['exception' => $e::class, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Reuse the id a proxy already assigned, so one request keeps one name
     * across every service it touches. Generate one when nobody did.
     */
    private function requestId(Request $request): string
    {
        $incoming = $request->headers->get(self::HEADER);

        return is_string($incoming) && $incoming !== '' && strlen($incoming) <= 128
            ? $incoming
            : (string) Str::ulid();
    }
}
