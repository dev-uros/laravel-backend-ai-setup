---
paths:
  - '{database/seeders/**,docs/**}'
---

# Seeders

## Demo data goes through the real services, with a faked clock
`DemoDataSeeder` (`make demo`) exists so the frontend has populated screens: eight accounts covering every state — never activated, switched off, promoted, renamed — plus ~28 audit entries and 100+ login attempts including failures and attempts on addresses nobody uses.

It is deliberately not registered in `DatabaseSeeder`. A fresh install seeds one real administrator; demo data is opt-in.

History is written through `RecordUserLogService` and `RecordLoginAttemptService`, never by inserting rows by hand, so the demo trail cannot drift from what the application actually produces — and the seeder breaks loudly if those signatures change. Only the clock is faked: each write runs inside `Carbon::setTestNow($moment)`, which backdates created_at, deactivated_at and login timestamps for free.

Spread events with `round($i * $span / ($times - 1))`, not an integer day step — an integer step piles every sign-in into the oldest third of the range and leaves the top of the list looking abandoned.

It stops if its marker account already exists, so re-running is safe. `DemoDataSeederTest` covers the states, the documented password, the date spread and idempotency; `docs/frontend-integration.md` lists the accounts for whoever builds the SPA.
