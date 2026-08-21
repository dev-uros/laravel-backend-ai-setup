---
paths:
  - '{app/Services/User/**,app/Policies/**,tests/**}'
---

# Policies

## Role changes live on their own endpoint and forbid self-changes
`PUT /users/{id}/role` (UpdateUserRoleController → UpdateUserRoleService) is the only way to change an existing user's role. It is deliberately off `PUT /users/{id}`, so a profile edit is never also a privilege grant.

An administrator cannot change their own role (CannotChangeOwnRoleException, 403). That single rule is also what stops the last administrator from vanishing — demoting one requires a second one, who remains an administrator afterwards. There is intentionally no separate "last admin" check; verify it is reachable before adding one.

The policy grants the ability (`changeRole`); who may be changed is the service's business, because it depends on the target. The controller passes `$request->user()->id` — services never call auth().

A role change does not revoke tokens: the policy reads the role from the database each request, so it applies from the next one.

In tests, an endpoint that reads something off the caller needs a persisted caller: `$this->actingAsAdmin(User::factory()->admin()->create())`. The bare helper signs in an unsaved user whose id is null, which surfaces as a TypeError, not a failed assertion.
