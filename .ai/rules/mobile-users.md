---
paths:
  - '{app/Models/MobileUser*.php,app/Models/MobileOtpCode.php,app/Services/MobileAuth/**,app/Services/MobileUser/**,app/Services/MobileUserDevice/**,app/Http/Controllers/Mobile/**,app/Http/Requests/MobileAuth/**,app/Http/Requests/MobileUser/**,app/Http/Middleware/Ensure*User.php,app/Http/Middleware/SetLocale.php,app/Support/IdToken/**,app/Support/Google/**,app/Support/Apple/**,routes/api.php,lang/**}'
---

# Mobile Users (the external app)

## Two populations, one guard -- every authenticated group states its audience
`users` (internal staff) and `mobile_users` (the external app's customers) both mint Sanctum tokens, and `auth:sanctum` resolves a token to whichever model minted it. So the internal group carries `EnsureInternalUser` (before `EnsureAccountIsActive`, which calls `isActive()` -- a method only `User` has) and the `mobile/v1` group carries `EnsureMobileUser` (which also refuses a blocked account on every request). `AudienceSeparationTest` walks **every** route: each `auth:sanctum` route must have exactly one of the two, and each is hit with the other population's token expecting 403. A new authenticated route goes inside one of the two groups, never beside them.

## Sign-in: separate routes, the server says whether an account exists
Decided with the user (2026-11-24), not an oversight: `email/sign-in-code` answers `account_exists`, and `google|apple/sign-in` answer 404 when there is no account -- the app takes people to registration. The per-address and per-IP limiters (`mobile-code-request`, `mobile-code-check`, `mobile-provider` in AppServiceProvider) keep a sweep slow. Errors are told apart by **status**, never by message text: 404 register, 409 account exists / not linkable (sign in with a code), 422 bad code or token, 429 wait (`Retry-After`), 503 provider not configured.

Codes (`mobile_otp_codes`): one row per address, bcrypt hash only, 10 minutes, 5 attempts. `claimAttempt` is one conditional UPDATE (`attempts < MAX`) **before** the hash is compared -- do not turn it into read-then-write. `VerifyOtpCodeService` must run **outside** any transaction, or the counter rolls back with the throw. A new code inside 30 s of the last (`MobileOtpCode::RESEND_AFTER_SECONDS`) is a 429 (`OtpResendTooSoonException`). The mail is `ShouldBeEncrypted`; `api/mobile/v1/auth/*` bodies are dropped from the request log and `code`/`id_token`/`nonce`/`authorization_code` are in `RequestContext::REDACTED`.

Demo accounts (`mobile:demo-accounts`, `MOBILE_DEMO_EMAILS`/`MOBILE_DEMO_CODE`) get the fixed code and no mail. The branch is chosen by `is_demo` on the account, never by the code.

## Tokens never expire -- on purpose
The user's decision for the external app: no `expiration`, no prune. Sign-out deletes that token and clears that phone's push token; blocking and deleting the account delete all tokens. Do not add expiry without asking.

## Google and Apple
Offline JWKS checks (`JwksIdTokenDecoder`): `aud` is a **list** (`GOOGLE_OAUTH_CLIENT_IDS`; `APPLE_CLIENT_IDS` = bundle IDs of all three app variants). Apple's `nonce` claim must equal sha256(raw nonce from the request). Linking an existing account by address only when the provider is authoritative (Gmail / `hd`; iCloud) and through a conditional UPDATE (`whereNull('google_id')`). Apple's authorization code is traded for a refresh token **after** the sign-in commits (a 404 leaves it unspent for registration); `apple_client_id` remembers which bundle ID, because Apple refuses a revoke under another. Tests use `Tests\Concerns\IssuesIdTokens` (real decoder, fake key server) and mock `AppleTokenClientInterface`.

## Deletion anonymises; usernames and emails are stored lower-cased
`DeleteMobileUserService` nulls every personal column and soft deletes, so the address and handle are free again at once (NULL under a unique index). Domain data a project adds is cleaned there, in the same transaction. Usernames and emails are lower-cased in `prepareForValidation` (`NormalizesMobileIdentity`) and by every service, so plain unique indexes are case-insensitive -- never compare them any other way.

## Language
`SetLocale` (mobile routes only) reads `Accept-Language`: `sr` default, `en`. Every message is `__('mobile.*')`; field names live in `lang/{sr,en}/validation.php` `attributes`, never in a FormRequest. `MobileLocaleTest` checks both files have the same keys. The account's `locale` (mail, pushes) is updated on every sign-in and by `PATCH /me`. Tests: Symfony's client sends `Accept-Language: en-us` by default -- call `$this->inSerbian()`.
