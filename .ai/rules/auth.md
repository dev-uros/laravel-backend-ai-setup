---
paths:
  - '{app/Services/Auth/**,app/Http/Controllers/AuthController.php,app/Data/Auth/**}'
---

# Auth

## Auth is Sanctum bearer tokens, not cookie SPA mode
The frontend is a separate SPA that logs in with email/password and gets a token back. That means Sanctum **personal access tokens** (`$user->createToken('spa')->plainTextToken`), sent as `Authorization: Bearer {token}`. Do not switch to Sanctum's stateful cookie/SPA mode — it returns no token and needs shared-domain cookies plus CSRF, which this setup does not have.

`POST /api/auth/login` answers `{message, data: {user, token}}` via `AuthenticatedUserResource`. Protect routes with the `auth:sanctum` middleware.

Bad credentials throw `InvalidCredentialsException` (401), never a 422 — 422 stays reserved for malformed input, so a client can tell "this field is wrong" from "these details are wrong" by status alone.

Two things must stay true of a failed login, and both have tests:
- the message never says whether the email or the password was wrong;
- an unknown email and a wrong password take the same time. `LoginService::TIMING_EQUALISER` exists only so a missing user still pays for one `Hash::check`. Removing it makes the endpoint an email-enumeration oracle.

The login route is throttled (`throttle:10,1`). Keep a throttle on any endpoint that rewards guessing.

Sanctum stores a SHA-256 of the token; the plain value is returned once and never again. Tokens do not expire by default — set `config/sanctum.php` `expiration` if that is wanted.
