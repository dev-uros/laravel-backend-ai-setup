<?php

namespace App\Logging;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Turns one finished request into the flat structure that ends up as a JSON log
 * line, ready to be scraped into Loki.
 *
 * Infrastructure, not a use case: no service, no repository, no database. It is
 * the one place that decides what a request is allowed to say about itself, so
 * the redaction list below is the only thing standing between the log stream and
 * somebody's password.
 */
class RequestContext
{
    /**
     * Field names whose value never reaches the log, at any depth.
     *
     * Public because RedactProcessor applies the same list to every other line
     * anybody writes by hand. One list, or the two drift and the weaker one is
     * the one that leaks.
     *
     * @var array<int, string>
     */
    public const REDACTED = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'secret',
    ];

    /**
     * Requests whose body is dropped whole rather than redacted field by field.
     *
     * Everything posted to these is either a credential or a token, so there is
     * nothing left worth keeping once the secrets are gone.
     *
     * @var array<int, string>
     */
    private const BODY_OMITTED_FROM = ['api/auth/*'];

    /**
     * How much encoded body is kept. Past this the body is dropped and its size
     * recorded instead -- a truncated payload is rarely useful, and a large one
     * costs the same to store whether or not anybody reads it.
     */
    private const MAX_BODY_BYTES = 8192;

    /**
     * A response slower than this is flagged, so "show me the slow ones" is a
     * label match rather than a quantile calculation.
     */
    private const SLOW_MS = 1000;

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request, Response $response, string $requestId, float $startedAt): array
    {
        $route = $request->route();
        $durationMs = round((microtime(true) - $startedAt) * 1000, 2);

        return array_filter([
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'path' => '/'.ltrim($request->path(), '/'),
            /**
             * The route pattern, not just the path. `/api/users/12/role` is
             * unbounded in cardinality and cannot be grouped; `users/{id}/role`
             * is what a dashboard aggregates on.
             */
            'route' => $route?->uri(),
            'route_name' => $route?->getName(),
            'route_params' => $this->redact($route?->parameters() ?? []),
            'query' => $this->redact($request->query()),
            ...$this->body($request),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'slow' => $durationMs >= self::SLOW_MS ?: null,
            'bytes' => strlen((string) $response->getContent()),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
            'user_role' => $request->user()?->role->value,
            'error' => $this->error($response),
        /**
         * Empty arrays are dropped along with nulls: they say nothing, and
         * an absent key parses more predictably than one that is an object
         * on some lines and `[]` on others.
         */
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * The level a status deserves.
     *
     * 401, 403 and 429 are warnings rather than noise: one is unremarkable, a
     * thousand from one address is the thing you want to notice.
     */
    public function level(Response $response): string
    {
        return match (true) {
            $response->getStatusCode() >= 500 => 'error',
            in_array($response->getStatusCode(), [401, 403, 429], true) => 'warning',
            default => 'info',
        };
    }

    /**
     * Whether this request is worth a line at all.
     *
     * Health checks and dashboard polling arrive constantly and say nothing;
     * left in, they are most of the log volume and all of the noise.
     */
    public function shouldLog(Request $request): bool
    {
        if ($request->getMethod() === 'OPTIONS') {
            return false;
        }

        return ! $request->is('up', 'horizon/api/*', 'pulse*');
    }

    /**
     * The request body, redacted, capped, or dropped.
     *
     * @return array<string, mixed>
     */
    private function body(Request $request): array
    {
        if ($request->is(...self::BODY_OMITTED_FROM)) {
            return ['body_omitted' => true];
        }

        $body = $request->isJson() ? $request->json()->all() : $request->post();
        $files = $this->files($request);

        if ($body === [] && $files === []) {
            return [];
        }

        $body = $this->redact($body);
        $encoded = strlen((string) json_encode($body));

        if ($encoded > self::MAX_BODY_BYTES) {
            return ['body_truncated' => true, 'body_bytes' => $encoded] + ($files === [] ? [] : ['files' => $files]);
        }

        return ['body' => $body] + ($files === [] ? [] : ['files' => $files]);
    }

    /**
     * Uploads by name and size. Never the contents -- that is how a log stream
     * ends up holding somebody's scanned passport.
     *
     * @return array<string, array{name: string|null, bytes: int}>
     */
    private function files(Request $request): array
    {
        $files = [];

        foreach ($request->allFiles() as $key => $file) {
            if ($file instanceof UploadedFile) {
                $files[$key] = ['name' => $file->getClientOriginalName(), 'bytes' => $file->getSize()];
            }
        }

        return $files;
    }

    /**
     * Replace the value of anything sensitive, at any depth, keeping the key so
     * the shape of the request is still readable.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = match (true) {
                is_string($key) && in_array(strtolower($key), self::REDACTED, true) => '[redacted]',
                is_array($value) => $this->redact($value),
                default => $value,
            };
        }

        return $values;
    }

    /**
     * What went wrong, when something did.
     *
     * For a validation failure only the field names are kept: which fields were
     * rejected is the useful part, and their values are exactly what must not be
     * written down.
     *
     * @return array<string, mixed>|null
     */
    private function error(Response $response): ?array
    {
        /** @var Throwable|null $exception */
        $exception = $response->exception ?? null;

        if ($exception === null) {
            return null;
        }

        if ($exception instanceof ValidationException) {
            return [
                'type' => $exception::class,
                'fields' => array_keys($exception->errors()),
            ];
        }

        return [
            'type' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile().':'.$exception->getLine(),
        ];
    }
}
