---
paths:
  - '{app/Services/**,app/Http/Controllers/**}'
---

# Services Http Controllers

## Every write to a user records an audit entry
A use case that writes to a user account calls `RecordUserLogService` inside the same `DB::transaction()` as the change, with a `UserLogEvent` case and only the fields that moved. Adding a use case that touches a user without an entry leaves a silent hole in the trail — add the enum case too.

The actor is `App\Data\ActorData`, built in the controller: `fromRequest()` for authenticated endpoints, `guest()` where nobody is signed in yet (activate-account, reset-password), `system()` off the request cycle. Services still never call auth() or request(). Where the account acts on itself, the service attributes the entry with `$actor->actingAs($user->id)` once the token has been verified.

`UpdateUserService` diffs against a TRACKED whitelist and records nothing when a request changes nothing — an entry saying "nothing happened" buries the ones that matter.

Read it back with `GET /users/{id}/logs` (admin only, `viewLogs`), paginated 25 per page.

Tests calling `SendActivationLinkService` directly must pass `ActorData::system()`.
