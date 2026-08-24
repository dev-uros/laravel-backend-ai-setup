# Project Guidelines

<!--
  This section lives OUTSIDE the Boost guidelines block on purpose.
  `php artisan boost:install` regenerates only the content between the
  laravel-boost-guidelines open/close markers, so anything written here survives
  a reinstall. Do not move it inside them.

  Never write those marker tags literally in this section: Boost matches them
  with a non-greedy regex, so a stray opening tag above the real block would
  make a reinstall replace everything from that tag down to the real closing
  tag -- silently deleting these guidelines.

  Keep this file short. It is loaded on every request, whether or not it is
  relevant. Anything that only matters while editing particular files belongs
  in .ai/rules/ (loaded by glob) or docs/ (loaded on demand) instead.
-->

## Read the rules for what you are touching

`.ai/rules/` holds the settled decisions and the traps, grouped by the paths
they apply to. **Open `.ai/rules/index.md`, find every row whose globs match the
file you are about to touch, and read those files before writing code.** They
carry the reasoning this file does not repeat — why a check runs before the
broker and not inside it, why `middlewareFor` and not `middleware`, why the
avatar file is written before the row. Also run `grep -rin '<keyword>' .ai/rules`
for anything a path match alone would miss.

Longer prose lives in `docs/`: `frontend-integration.md` (the SPA contract) and
`deployment.md` (the production runbook). Both are part of the contract — a
change to a response shape or to the deploy flow is edited there in the same
commit.

## This application runs in Docker

Seven services in `compose.yaml`: `nginx` -> `php` (php-fpm), plus `horizon`,
`scheduler`, `postgres`, `postgres-test` and `redis`. They build the `dev`
target of `docker/Dockerfile`, which also holds the production stages — one file
on purpose, so the PHP version and extension list cannot drift between dev and
prod.

**Never run `php`, `composer`, `artisan` or `phpunit` on the host.**
`DB_HOST=postgres` and `REDIS_HOST=redis` only resolve inside the Compose
network, so host-side commands that touch the database, cache or queue fail to
connect.

| Instead of | Run |
| --- | --- |
| `php artisan <cmd>` | `make artisan c="<cmd>"` |
| `composer <cmd>` | `make composer c="<cmd>"` |
| a shell | `make php` |

`make` execs as `www-data` remapped to your uid, so files written into the bind
mount stay owned by the host user; exec'ing as root leaves root-owned files on
Linux. Run `make` with no arguments for the full list of targets.

Production deploys with `./deploy.sh` (`make deploy`) on the server — never a
bare `up -d`. See `docs/deployment.md` and `.ai/rules/docker.md`.

## Architecture: controllers stay thin

Business logic lives in **service classes**. Data access lives in
**repositories behind interfaces**. These are hard rules, not preferences.

```
route -> FormRequest (validation) -> Controller -> DTO -> Service
                                                             |
                                                          Repository -> Model
                                                             |
Controller <- returns model/collection <---------------------+
     |
   API Resource -> ApiResponse -> JSON
```

**Controller** — builds the DTO, calls one service, wraps the result. No
Eloquent, no `DB::`, no business logic, no inline validation, no `try/catch`
around domain errors. Services are injected **per action**, not through the
constructor. If an action needs a conditional beyond mapping a result to a
response, that conditional belongs in the service.

**FormRequest** — the only place validation is written.

**Service** — owns the business logic and the transaction. One class per use
case, one public `handle()`. Receives a DTO, never the `Request`; returns models,
collections or primitives, never an HTTP response. Never calls `auth()`,
`request()` or `session()` — the controller passes what it needs. Talks to
repository interfaces, not to Eloquent. Signals failure by throwing a domain
exception that extends the matching Symfony HTTP exception, never by returning
null or a flag.

**Repository** — the only place Eloquent is touched for its model. Pure data
access: no business rules, no validation, no events. Every model gets one, plus
its interface, registered in `RepositoryServiceProvider`. Always type-hint the
**interface**.

Naming is fixed: `{Model}Controller`, `{Action}{Model}Service`,
`{Action}{Model}Data`, `{Model}Repository`, `{Model}RepositoryInterface`.
Plural for collections — `IndexUsersService`. A use case with no input has no
DTO, and route parameters pass as plain scalars.

The one deliberate exception is request logging (`app/Logging/`,
`LogRequests`): infrastructure, not a use case, and it touches no database. Do
not invent a service and repository for it.

## What the API is, in one screen

- **Auth is Sanctum personal access tokens**, not the stateful cookie/SPA mode.
  `POST /api/auth/login` returns the token in the body; the SPA sends it as
  `Authorization: Bearer`. Everything authenticated sits in the one
  `auth:sanctum` group in `routes/api.php` — add new routes there rather than
  repeating the middleware.
- **Every response goes through `App\Http\Responses\ApiResponse`** —
  `{message, data}`, `message` always first and written for a human in the
  project's user-facing language, `data` always present and `null` when empty.
  Never return a bare array, model or resource. Laravel's own error responses
  (422, 401, 403, 404, uncaught) are deliberately left in the framework's shape.
- **Two roles** in `App\Enums\UserRole`, enforced by `can:` middleware on the
  routes against `App\Policies\UserPolicy`. A new endpoint needs a policy method
  and a `can:` or it is open to every signed-in user.
- **Every write to a user account records a `user_logs` entry** in the same
  transaction, and every sign-in attempt records a `login_attempts` row.
- **Emailed links point at `config('app.frontend_url')`**, never `APP_URL` —
  this API renders no pages.

Several security properties are load-bearing and covered by tests that will look
like they are testing nothing: the login timing equaliser, the byte-identical
`forgot-password` response, the deactivation checks that run before the token
broker. **Do not simplify anything in those flows without reading
`.ai/rules/auth*.md` and `.ai/rules/services-auth.md` first.**

## There is no frontend build step

**This overrides the Boost "Frontend Bundling" and "Vite Error" sections below.**

Vite, Tailwind, npm and `node_modules` were removed. There is no `package.json`,
no build, no Node container. What lives here is one Blade page at `/` styled by
hand-written CSS in `public/css/app.css`, linked with `asset()`. Never add
`@vite` to a view and never reintroduce npm to serve a page. Horizon and Pulse
inline their own assets — an unstyled page there is the package's published
assets, not a missing build. Reinstating a toolchain is a decision to raise with
the user, not a fix for a styling issue.

## Running tests

**This overrides the "Running Tests" rules in the Boost PHPUnit section below.**

Tests run against a real PostgreSQL server (`postgres-test`), not SQLite, so
Postgres behaviour (jsonb, case sensitivity, strict types) is actually
exercised.

| Goal | Command |
| --- | --- |
| Whole suite | `make test` |
| One file | `make artisan c="test --compact tests/Feature/ExampleTest.php"` |
| One test | `make artisan c="test --compact --filter=testName"` |

`make test` waits for `postgres-test`; when filtering with `make artisan`, bring
the stack up first. PHPUnit 12 — attributes, not docblock annotations. Read
`.ai/rules/tests.md` before writing a test: it covers authenticating, the traps
that make a suite pass against broken code, and what must be stubbed.

## Configuration facts worth knowing

- PostgreSQL 18. The Compose volume mounts `/var/lib/postgresql`, **not** the
  pre-18 `/var/lib/postgresql/data`, which still boots but persists nothing.
- Cache and queue are Redis on separate databases (`REDIS_CACHE_DB=1`,
  `REDIS_DB=0`), so `cache:clear` cannot drop queued jobs. Sessions are in
  Postgres.
- Queues run in the `horizon` container; after changing job code,
  `make horizon-restart`. Scheduled tasks run in `scheduler` and need no restart.
- Postgres is published on host port **5433**, the test database on **5434**.
- API docs are generated by Scramble at `/docs/api` — no annotations to maintain,
  but adding a method to `ApiResponse` means adding it to
  `App\Support\Scramble\ApiResponseTypeInfer`.
- `make demo` seeds eight accounts and six weeks of history; `make observe`
  starts the dev Loki/Grafana stack on `localhost:3000`.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
