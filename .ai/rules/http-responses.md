---
paths:
  - '{routes/api.php,app/Http/Resources/**,app/Http/Requests/**,app/Http/Responses/**,docs/**}'
---

# Http Responses

## Changing the API contract means updating the frontend guide
`docs/frontend-integration.md` is the step-by-step guide the separate SPA at localhost:5173 follows. It documents the `{message, data}` envelope, the bearer token flow, the two frontend routes emailed links point at (`/auth/activate-account`, `/auth/reset-password`), which endpoints are admin-only, the paginated shape, and what the client should do per status code.

Treat it as part of the contract: a changed response shape, a new required field, a moved status code or a new endpoint is edited there in the same change. Scramble's `/docs/api` is generated and cannot go stale, but this file can, and a second project depends on it.

Facts in it that were verified against the running app and should stay true: CORS allows exactly one origin, derived from `FRONTEND_URL` (override with a comma-separated `CORS_ALLOWED_ORIGINS`), and exposes `X-Request-Id` so the SPA can read it back; every response carries `X-Request-Id`; throttled endpoints return 429 with `Retry-After: 60`; validation errors keep Laravel's `{message, errors}` shape while successes keep the envelope.
