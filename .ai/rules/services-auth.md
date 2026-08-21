---
paths:
  - '{app/Services/User/**,app/Http/Middleware/**,app/Services/Auth/**}'
---

# Services Auth

## Accounts are switched off, never soft deleted
Disabling an account sets `deactivated_at` on `users` (null = active) via `POST /users/{id}/deactivate`; `/reactivate` clears it. Do not reach for SoftDeletes here: a disabled account must stay visible in the users list so an administrator can switch it back on, and a soft-deleted row still occupies the unique email index, so that address could never be reused and `restore()` would collide.

`DeactivateUserService` revokes every token the account holds in the same transaction as the stamp — tokens do not expire, so without that the user stays signed in.

Six places must agree, each with a test: EnsureAccountIsActive on the auth:sanctum group (403 for a token that survived), LoginService (403, only AFTER the password check, or it becomes an enumeration oracle), ForgotPasswordService (skipped silently, byte-identical 200), ResetPasswordService and ActivateAccountService (refused as an invalid token, NOT with the deactivated message — those checks run before the token is verified, so a distinct answer would let anyone probe which accounts are off), ResendActivationService (403, admin-only endpoint).

Reactivation does not restore revoked tokens; the user logs in again.
