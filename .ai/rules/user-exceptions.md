---
paths:
  - '{app/Services/User/**,app/Exceptions/**}'
---

# User Exceptions

## Nobody may switch off or demote their own account
Two endpoints refuse to act on the caller themselves: `POST /users/{id}/deactivate` (CannotDeactivateOwnAccountException) and `PUT /users/{id}/role` (CannotChangeOwnRoleException), both 403.

Together they are the whole guarantee that an active administrator always exists. Only an active administrator reaches either endpoint, and they are still an active administrator after acting on somebody else, so the count can never reach zero. There is deliberately no "last administrator" check in the codebase — it would be unreachable. Verify that reasoning still holds before adding one.

Both services take the caller's id as a parameter (`handle(int $id, int $actorId)`); the controller reads `$request->user()->id`. Services never call auth().

Tests for these endpoints must sign in a persisted administrator — `$this->actingAsAdmin(User::factory()->admin()->create())` — because the bare helper's user has a null id.
