---
paths:
  - '{app/Services/Auth/**,app/Models/LoginAttempt.php,routes/console.php}'
---

# Auth Models

## Login attempts are their own table, and every branch writes one row
Sign-in attempts go to `login_attempts`, not to `user_logs`. An attempt on an address nobody uses has no account to point at, and making `user_logs.user_id` nullable for it would weaken the column that trail rests on; logins are also far higher volume and pruned on a different schedule.

Every branch out of `LoginService` records exactly one row — success, wrong password, unknown address, deactivated account. An extra or missing insert on one path reintroduces the timing difference `TIMING_EQUALISER` removes, so a test counts the rows.

Both failure kinds share the `invalid_credentials` outcome. Recording "no such user" apart from "wrong password" would put the distinction the endpoint refuses to reveal into a table someone will eventually expose; a test asserts they are indistinguishable. `account_deactivated` is only reachable after the password checks out.

`RecordLoginAttemptService` is never handed a password, so it cannot leak one. LoginRequest validates the address as an email first, so a password typed into the wrong field is a 422 and never recorded.

Both log tables are MassPrunable, on very different clocks: `LoginAttempt::RETENTION_DAYS` is 180 days, `UserLog::RETENTION_DAYS` is 1095. One daily `model:prune` in routes/console.php covers both and holds no opinion of its own -- how long to keep lives on each model. The `scheduler` container runs it.

Both tables carry a plain index on `created_at` purely for that prune: their other indexes all lead with another column, and MassPrunable deletes in chunks, so without it the cost is one sequential scan per chunk.

`GET /users/{id}/logins` reads the history back and needs the same `viewLogs` ability as the audit trail — same audience, same sensitivity, and one must not be opened up by accident while the other stays shut. It answers "what happened to this account", so the null-`user_id` rows for attempts on invented addresses are deliberately not reachable through it; querying for sweeps is a different question needing a different view.
