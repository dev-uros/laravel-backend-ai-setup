---
paths:
  - '{app/Support/Scramble/**,config/scramble.php,app/Http/Controllers/**}'
---

# Scramble Http Controllers

## Security scheme comes from middleware; hide unreachable statuses
`config/scramble.php` sets `security_strategy` to `MiddlewareAuthSecurityStrategy`. It reads `auth`/`auth:*` off the routes, declares the bearer scheme once in `components.securitySchemes`, and marks unprotected routes `security: []`. That is what renders the Authorize box in `/docs/api`; with the vendor default (`null`) a protected endpoint documents as public and cannot be tried from the page.

Scramble derives error responses from the exceptions a service throws, so an endpoint can document a status it cannot actually return. `#[Dedoc\Scramble\Attributes\IgnoreResponse(404)]` on `AuthController::me` removes one — `ShowUserService` throws 404, but a bearer token only resolves while its owner exists. Use it only for genuinely unreachable statuses, never for merely rare ones.
