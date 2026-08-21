---
paths:
  - '{app/Http/Controllers/**,app/Http/Responses/**}'
---

# Responses

## Controller responses go through ApiResponse
Every response a controller returns is built with `App\Http\Responses\ApiResponse`, giving `{"message": "...", "data": ...}`. Never return a bare array, model, resource or `response()->json()` from an action.

    return ApiResponse::created('Korisnik uspešno kreiran.', new UserResource($user));

`message` is always present, always first, and written for a human to read — it is shown to end users, so keep it in the project's user-facing language. `data` is always present too, `null` when there is no payload. `success()` is 200, `created()` is 201; any other status is the third argument to `success()`.

Pass API Resources straight in. ApiResponse renders the resource itself, so it is never nested into `data.data` and a paginated collection keeps `links` and `meta` beside `message` — do not call `->resolve()` or `->response()` yourself.

Laravel's own error responses are deliberately NOT reshaped: validation failures, 401, 403, 404 and uncaught exceptions keep the framework's shape. Do not touch the exception handler for this, and do not catch a domain exception in a controller just to re-emit it through ApiResponse — let it bubble.

Covered by tests/Feature/Http/Responses/ApiResponseTest.php; extend those when adding a method.
