---
paths:
  - 'app/Notifications/**'
---

# Notifications

## Notifications are queued and link to the frontend
Every notification implements `ShouldQueue` and uses the `Queueable` trait. A user-facing request must never wait on the mail transport; Horizon processes these off the Redis queue.

Links sent to a user point at the **frontend** application, never at this API. Build them from `config('app.frontend_url')` (env `FRONTEND_URL`), never from `APP_URL`, `url()` or `route()` — those resolve to the API host and give the user a dead link.

    rtrim((string) config('app.frontend_url'), '/')
        .'/auth/reset-password?'
        .http_build_query(['email' => $user->getEmailForPasswordReset(), 'token' => $this->token])

Use `http_build_query` rather than string concatenation so addresses are encoded (`petar@gmail.com` -> `petar%40gmail.com`).

Password reset tokens come from Laravel's broker (`Password::broker()->createToken($user)`), never hand-rolled. The broker stores a bcrypt hash and returns the plain token once; that plain value exists only in the email. Rolling your own means `Password::reset()` cannot validate it later.

Copy: user-facing subjects and body lines are written in the project's user-facing language, same as ApiResponse messages.
