---
paths:
  - '{app/Logging/**,app/Http/Middleware/LogRequests.php,bootstrap/app.php,config/logging.php}'
---

# Middleware

## Request logging is infrastructure, and redaction lives in one place
`LogRequests` (appended in bootstrap/app.php) writes one JSON line per finished request in `terminate()`, so encoding costs the caller nothing. `RequestContext` builds it. This is the one part of the app that deliberately skips the service/repository layers — it is infrastructure and touches no database. Do not add a service or repository for it.

`RequestContext::REDACTED` is the only redaction list; add to it rather than filtering anywhere else. The response body is never logged (login would leak a bearer token), `api/auth/*` bodies are dropped whole, bodies over 8 KB become `body_truncated` + `body_bytes`, uploads keep name and size only, and a validation failure records field names but never values.

Always log `route` (the pattern) beside `path`. In Loki, `request_id`/`user_id`/`path` belong in the line, never as labels — as labels every request becomes its own stream.

`request_id` is reused from an incoming `X-Request-Id`, returned in the response header, and put into `Context` so every other log line of that request — and of any queued job it dispatches — carries it.

The logger must never break a request: `terminate()` catches everything and degrades to one warning. A test binds a throwing RequestContext and asserts the response is unaffected.

`LOG_CHANNEL=stderr` + `LOG_STDERR_FORMATTER=Monolog\Formatter\JsonFormatter` sends all logs as JSON to the container log (php-fpm's docker.conf has catch_workers_output=yes, decorate_workers_output=no). phpunit.xml sets `LOG_CHANNEL=null` so the suite is not buried; MessageLogged still fires for tests to assert on.
