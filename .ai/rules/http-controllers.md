---
paths:
  - '{app/Services/Auth/**,app/Http/Controllers/AuthController.php,routes/api.php}'
---

# Http Controllers

## Logout revokes one token, and auth routes live in one group
`POST /api/auth/logout` deletes only `$request->user()->currentAccessToken()`, never `$user->tokens()`. An account can be signed in on several devices, each with its own token, so dropping them all signs the user out where they did not ask to leave. Wholesale revocation belongs to `ResetPasswordService`, where losing old sessions is the point.

The controller reads the token off the request and passes it to `LogoutService`, keeping the service free of ambient auth state — services never call auth().

`GET /api/auth/me` reuses `ShowUserService`, so it reads the row fresh rather than echoing the guard's copy.

Both are registered inside the single `Route::middleware('auth:sanctum')->group(...)` in routes/api.php. Add new authenticated routes to that group instead of repeating the middleware per route.
