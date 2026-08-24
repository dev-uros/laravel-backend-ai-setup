---
paths:
  - '{app/Models/UserLog.php,app/Services/UserLog/**,app/Data/ActorData.php,database/migrations/**}'
---

# Migrations

## Audit log: two user references, diffs not snapshots, same transaction
Changes to user data are recorded in `user_logs`. Design decisions worth keeping:

Two user references, never one — over users both sides are users, so a single `user_id` becomes unreadable within months. `user_id` is WHOSE record changed; `actor_id` is WHO changed it, nullable, where null means the system (seeder, command, scheduler) — not "unknown".

Store the changed fields only, as `old_values`/`new_values` jsonb, not a snapshot of the row and never as real columns (a new field on `users` would then need a migration on the log table forever). `event` carries the human sentence (`user.role_changed`); the jsonb carries the proof. `password` and `remember_token` are filtered out in `RecordUserLogService::NEVER_RECORDED` — both old and new. A hash is still the secret's shadow, and this table is read by more people than `users` is.

The log row is written inside the same transaction as the change it describes, never queued: a log that can fail while the change succeeds is a log nobody trusts. Rows are append-only, so there is no `updated_at`.

The actor arrives as `ActorData` (id, ip, user agent) built by the controller — services still never call auth()/request(). `ActorData::system()` covers console callers.

Known limit, accepted: nothing here catches changes made straight in the database or through tinker. Only Postgres triggers would, and they cannot name an application-level actor.

If a second model ever needs this, replace `user_id` with `auditable_type` + `auditable_id`; the rest of the shape carries over unchanged.

Neither foreign key on `user_logs` cascades, so deleting a user fails while their trail exists. That is deliberate: decide what happens to the history before deleting the account.
