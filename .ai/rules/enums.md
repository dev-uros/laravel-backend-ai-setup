---
paths:
  - '{app/Policies/**,app/Enums/**,routes/api.php}'
---

# Enums

## Roles are admin/user, enforced by can: middleware on the routes
`App\Enums\UserRole` has exactly two cases: `admin` may do everything, `user` may only read. The column is a plain string on `users` defaulting to `user`, cast to the enum on the model.

`App\Policies\UserPolicy` is applied by the `can:` middleware in routes/api.php, not inside controllers, so "who may reach this endpoint" is readable from the route file. `before()` returns true for admins; the methods below it describe what a plain user may do. A new endpoint needs a policy method and a `can:` middleware or it is open to every signed-in user.

On a resource route use `->middlewareFor('index', ...)`, never `->middleware(['index' => ...])`. A keyed array is flattened and applied to every action, so `can:create` would also guard `index`. It fails as a 403, which looks deliberate — nothing tells you it happened.

Policy methods take only the caller. The routes carry a plain `{id}`, so the middleware passes the class name and Gate drops it before the call. Rules that depend on the target row need the loaded model and belong in the service, with the caller passed in.

`store` accepts an optional `role`; `update` deliberately does not, because changing a role needs answers this template does not have (self-demotion, last remaining admin).
