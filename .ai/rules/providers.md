---
paths:
  - '{app/Logging/**,app/Jobs/**,app/Services/**,app/Providers/AppServiceProvider.php}'
---

# Providers

## Logging by hand: stable messages, and jobs are covered automatically
A hand-written log line uses a stable, low-cardinality message with the variable parts in the context array: `Log::info('activation link queued', ['user_id' => $id])`, never `Log::info("sent link to user $id")` — an interpolated id cannot be grouped, counted or alerted on.

Log decisions and outcomes, not steps. And do not duplicate `user_logs`: the audit trail is evidence for "who changed what"; the application log is operational for "what did the system do and why".

`App\Logging\JobLogger` (queue listeners registered in AppServiceProvider) is the queue's counterpart to the LogRequests middleware. It puts job/job_id/queue/connection/attempt into Context so every line a job writes carries them, emits one `job` line with outcome and duration, and a `job attempt failed` warning per throwing attempt. Do not add per-job logging boilerplate; extend JobLogger instead.

`request_id` propagates into jobs on its own — Laravel packs Context into the payload and hydrates it in the worker — so a request and the job it dispatched share one id across containers.

`App\Logging\RedactProcessor` is a Monolog processor on the channel and strips `RequestContext::REDACTED` keys from the context and extra of every record, whoever wrote it. Add new secret names to that one list, never to a second one.

Services may call the `Log` facade directly; the "no auth()/request() in a service" rule is about ambient state that breaks outside a request, which logging is not.
