---
paths:
  - '{routes/api.php,app/Http/Resources/**,app/Http/Requests/**,app/Http/Responses/**,docs/**}'
---

# Http Responses

## Changing the API contract means updating the frontend guide
`docs/frontend-integration.md` is the step-by-step guide the separate SPA at localhost:5173 follows. It documents the `{message, data}` envelope, the bearer token flow, the two frontend routes emailed links point at (`/auth/activate-account`, `/auth/reset-password`), which endpoints are admin-only, the paginated shape, and what the client should do per status code.

**It is not a changelog, and most changes do not touch it.** `/docs/api` is generated from the code and cannot go stale, so anything Scramble already derives — an endpoint's path, its fields, their types, the statuses, whether it needs a token — is discoverable there and must not be duplicated here. A new endpoint that behaves the way the others do needs no edit at all.

Edit it in the same change when, and only when:

- a change would **break a client already written against it** — a renamed or removed field, a changed response shape, a status code that moved;
- the behaviour would **surprise someone reading only the schema**: `PUT` with every field required and no `PATCH`, a list that is not paginated, a 200 that deliberately does nothing, an endpoint answering 401 where 422 was expected;
- **who may call it** changes. Scramble's `MiddlewareAuthSecurityStrategy` reads `auth:*` but not the `can:` middleware, so the admin/user split in section 7 is the only place that map exists;
- a **frontend route** changes — the two pages emailed links point at.

Facts in it that were verified against the running app and should stay true: CORS allows exactly one origin, derived from `FRONTEND_URL` (override with a comma-separated `CORS_ALLOWED_ORIGINS`), and exposes `X-Request-Id` so the SPA can read it back; every response carries `X-Request-Id`; throttled endpoints return 429 with `Retry-After: 60`; validation errors keep Laravel's `{message, errors}` shape while successes keep the envelope.

Prose in that file that restates something a test asserts is safe; prose that restates something no test touches is what drifts. `CorsTest` and `DemoDataSeederTest` exist because the file makes claims about both.
