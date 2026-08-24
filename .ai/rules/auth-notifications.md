---
paths:
  - '{app/Services/Auth/**,app/Notifications/**}'
---

# Auth Notifications

## Activation and password reset share one token table
Both flows mint tokens through Laravel's password broker, so they land in the same `password_reset_tokens` table and are indistinguishable there. Two consequences that are easy to get wrong:

1. `ResetPasswordService` refuses accounts with a null `email_verified_at`. Without that check an activation link could be spent on `reset-password` to get a working password while skipping email verification.
2. That check runs **before** the broker, because the broker deletes the token whether or not the callback does anything. Checking inside the callback would burn an unverified account's activation link on every refused attempt.

The two emails point at different frontend paths — `/auth/activate-account` and `/auth/reset-password` — so the SPA can tell which endpoint to post the token to. Do not collapse them.

`ConsumePasswordResetTokenService` holds the broker plumbing for both and returns `null` on a bad token; each caller throws its own exception so the message names the right flow.

`forgot-password` answers identically for a registered, unregistered, and unactivated address, and never throws. Anything else lets an anonymous caller harvest accounts. Covered by a test asserting all three responses are byte-identical.

A successful reset revokes every existing Sanctum token (`$user->tokens()->delete()`), because a reset is how someone recovers a hijacked account. Activation does not need to — there are none yet.

Activation posts `{email, token, password, password_confirmation}` to `/api/auth/activate-account`. `Password::broker()->reset()` does the verifying — it checks the token against its stored hash and deletes it once used, so a link works exactly once; do not hand-roll that check. Expired, spent, or another user's all throw `InvalidActivationTokenException` (422) with a message that does not say which. Activation sets `email_verified_at` and returns a bearer token, so the SPA does not have to bounce the user through the login form.

The password policy lives in the request classes: at least 8 characters, mixed case, a number, a symbol, and `uncompromised()`.
