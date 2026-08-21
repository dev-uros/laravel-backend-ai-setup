---
paths:
  - '{app/Models/UserLog.php,app/Models/LoginAttempt.php,routes/console.php}'
---

# Models Models

## Retention lives on the model, and prune needs a created_at index
Both log tables are pruned on their own clock: `LoginAttempt::RETENTION_DAYS` is 180, `UserLog::RETENTION_DAYS` is 1095. A single daily `model:prune` in routes/console.php lists both models and decides nothing itself — change how long something is kept on the model, never in the schedule.

The three-year figure for the audit trail is a policy decision, not a technical one. "Who has been signing in lately" goes stale in weeks; "who changed this address and when" is asked during a dispute or an audit, long after the fact.

Every prunable log table needs a plain index on `created_at`. The other indexes on these tables all lead with another column (`user_id`, `email`, `ip_address`), which a bare `where created_at <= ?` cannot use, and MassPrunable deletes in chunks of 1000 — so an unindexed prune is one sequential scan per chunk, growing exactly as the table gets big enough to need pruning. On a small dev table the planner will still choose a seq scan; that is correct, not a missing index.
