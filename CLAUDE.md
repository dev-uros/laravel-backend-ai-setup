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
-->

## This application runs in Docker

Seven services, defined in `compose.yaml`: `nginx` -> `php` (php-fpm), plus
`horizon`, `scheduler`, `postgres`, `postgres-test` and `redis`.

They build the `dev` target of `docker/Dockerfile`, which also holds the
production stages. One file on purpose: the PHP version and the extension list
are the two things dev and prod must never disagree on, and a second Dockerfile
is how an extension gets added in one place and forgotten in the other. See
**Deploying to production** below.

**Never run `php`, `composer`, `artisan` or `phpunit` on the host.**
`DB_HOST=postgres` and `REDIS_HOST=redis` only resolve inside the Compose
network, so host-side commands that touch the database, cache or queue will
fail to connect. Go through the Makefile:

| Instead of | Run |
| --- | --- |
| `php artisan <cmd>` | `make artisan c="<cmd>"` |
| `composer <cmd>` | `make composer c="<cmd>"` |
| a shell | `make php` |

`make` exports `WWWUSER`/`WWWGROUP` from `id -u`/`id -g` and execs as
`www-data`, which is remapped to that uid at build time, so files written into
the bind mount stay owned by the host user. Bypassing `make` and exec'ing as
root leaves root-owned files on Linux.

Run `make` with no arguments for the full list of targets.

## Architecture: controllers stay thin

Business logic lives in **service classes**. Data access lives in
**repositories behind interfaces**. These are hard rules, not preferences.

### The request flow

```
route -> FormRequest (validation) -> Controller -> DTO -> Service
                                                             |
                                                          Repository -> Model
                                                             |
Controller <- returns model/collection <---------------------+
     |
   API Resource -> ApiResponse -> JSON
```

A controller action does exactly three things: build the DTO, call the service,
wrap the result in a response. Nothing else.

One resource, one controller: `UserController` with `index`, `store`, `show`,
`update`, `destroy`. Services are injected **per action**, not through the
constructor, so a request only resolves the one it uses.

```php
class UserController extends Controller
{
    public function store(StoreUserRequest $request, StoreUserService $storeUser): JsonResponse
    {
        return ApiResponse::created(
            'Korisnik uspešno kreiran.',
            new UserResource($storeUser->handle(StoreUserData::fromRequest($request))),
        );
    }
}
```

Reach for a single-action `__invoke` controller only when an action genuinely
falls outside the resource — a different verb, its own middleware, its own
authorisation.

### Routes

One `apiResource` per resource in `routes/api.php`, with the parameter renamed
to `id` because this project does not use route model binding — the repository
is the only thing allowed to load a model.

```php
Route::apiResource('users', UserController::class)
    ->only(['index', 'store', 'show'])
    ->parameters(['users' => 'id'])
    ->whereNumber('id');
```

`whereNumber` keeps non-numeric ids from ever reaching the application.

`update` is registered separately as `PUT` only. `apiResource` would also
register `PATCH`, and every field on that endpoint is required — advertising
`PATCH` would promise a partial update that answers 422. Register an action
outside the resource whenever the verbs it should accept differ from the default.

### The response envelope

Everything a controller returns goes through `App\Http\Responses\ApiResponse`.
Never return a bare array, model or resource from an action.

```json
{
  "message": "Korisnik uspešno kreiran.",
  "data": { "id": 1, "name": "Uroš", "surname": "Minić" }
}
```

`message` is always present, always first, and always written for a human to
read. `data` is always present too — `null` when the action has no payload.
`ApiResponse::success()` is 200, `ApiResponse::created()` is 201; pass any other
status as the third argument.

Pass the API Resource straight in — `ApiResponse` renders it, so a resource is
never nested into `data.data`, and a paginated collection keeps its `links` and
`meta` next to `message`.

**Laravel's own error responses are deliberately left alone.** Validation
failures, 401, 403, 404 and uncaught exceptions keep the shape the framework
gives them. Do not reshape them, and do not catch a domain exception in a
controller just to re-emit it through `ApiResponse`.

### Domain errors

A service signals failure by throwing, never by returning null or a flag. The
exception extends the Symfony HTTP exception matching the status it should
produce:

```php
class UserNotFoundException extends NotFoundHttpException
{
    public function __construct()
    {
        parent::__construct('Korisnik nije pronađen.');
    }
}
```

```php
return $this->users->findById($id) ?? throw new UserNotFoundException;
```

Laravel then renders it in its native error shape with a message written for a
person, instead of the default `No query results for model [App\Models\User] 5`
which leaks the class name. Scramble also documents the status automatically,
because it recognises the Symfony parent class.

The controller does not catch it. `bootstrap/app.php` is not touched for this.

### Side effects: transactions and queued mail

A service that writes more than one row owns the transaction. Anything sent to
the outside world is queued, and dispatched **after** those rows are committed —
otherwise a worker can pick the job up before they exist:

```php
$user = $this->users->create($data, Str::password(32));

$this->activationLink->handle($user);   // mints a token, queues the email
```

Work shared by more than one use case gets its own service instead of being
copied. `SendActivationLinkService` is called both when an account is created
and when support resends the link, so the two can never drift apart.

Links emailed to a user point at `config('app.frontend_url')` (env
`FRONTEND_URL`), never at `APP_URL` — this API does not render pages for them.

### Layout

```
app/
  Data/User/StoreUserData.php              DTO, readonly, typed properties
  Data/ActorData.php                       who is behind a change, and from where
  Enums/LoginOutcome.php                   how a login attempt ended
  Enums/UserLogEvent.php                   what happened to an account
  Enums/UserRole.php                       admin or user, cast on the model
  Exceptions/UserNotFoundException.php     domain errors, rendered by Laravel
  Notifications/AccountCreatedNotification.php  queued, links to the frontend
  Http/Middleware/EnsureAccountIsActive.php  second lock on a disabled account
  Http/Middleware/LogRequests.php          one JSON line per finished request
  Logging/JobLogger.php                    the queue's half of the same thing
  Logging/RedactProcessor.php              strips secrets from every line written
  Logging/RequestContext.php               what a request may say about itself
  Http/Requests/User/StoreUserRequest.php  validation + authorize
  Http/Controllers/AuthController.php      login
  Http/Controllers/UserController.php      one per resource, thin actions
  Http/Controllers/User/ResendActivationController.php  __invoke, outside the resource
  Http/Resources/UserResource.php
  Http/Responses/ApiResponse.php           the {message, data} envelope
  Models/LoginAttempt.php                  one sign-in attempt, kept 180 days
  Models/User.php
  Models/UserLog.php                       one append-only entry, kept 3 years
  Repositories/Contracts/UserRepositoryInterface.php
  Repositories/UserRepository.php          implements the interface
  Services/User/StoreUserService.php       one class per use case, one handle()
  Services/User/UpdateUserAvatarService.php    re-encodes the upload, swaps the file
  Services/User/AuthorizeAvatarChangeService.php  whose avatar a caller may change
  Services/User/SendActivationLinkService.php  shared by store and resend
  Services/UserLog/RecordUserLogService.php    the only way into the trail
  Policies/UserPolicy.php                  applied by `can:` middleware on the routes
  Providers/RepositoryServiceProvider.php  binds interface -> implementation
  Support/Scramble/ApiResponseTypeInfer.php  teaches the docs generator the envelope
```

Naming is fixed: `{Model}Controller`, `{Action}{Model}Service`,
`{Action}{Model}Data`, `{Model}Repository`, `{Model}RepositoryInterface`.
Services acting on a collection take the plural — `IndexUsersService`.

A use case with no input has no DTO: `IndexUsersService::handle()` takes no
arguments. Route parameters are passed as plain scalars —
`ShowUserService::handle(int $id)`. DTOs are for request payloads; do not invent
one to wrap a single id.

### Rules per layer

**Controller** — no Eloquent, no `DB::`, no query builder, no business logic, no
`try/catch` around domain errors. If an action needs a conditional beyond
mapping a result to a response, that conditional belongs in the service.

**FormRequest** — the only place validation is written. Never
`$request->validate()` inline, never validation inside a service.

**Service** — owns the business logic and the database transaction. Receives a
DTO, never the `Request` object; returns models, collections or primitives,
never an HTTP response. Does not call `auth()`, `request()` or `session()` — the
controller passes what the service needs. Talks to repositories, not to Eloquent.

**Repository** — the only place Eloquent is touched for its model. Pure data
access: no business rules, no validation, no events. Returns models and
collections. Every model gets one, plus its interface.

**Binding** — register every interface -> implementation pair in
`RepositoryServiceProvider`. Always type-hint the **interface** in constructors,
never the concrete repository.

Details and worked examples are in `.ai/rules/` — read them before adding a
controller, service or repository.

## Authentication

Sanctum **personal access tokens**, not Sanctum's stateful cookie/SPA mode — the
frontend is a separate origin and expects a token in the response body.

```
POST /api/auth/login   {email, password}
-> {message, data: {user: {...}, token: "1|K4wrWt..."}}
```

The SPA sends it back as `Authorization: Bearer {token}`. Protect routes with
the `auth:sanctum` middleware.

Bad credentials answer **401**, not 422: 422 stays reserved for malformed input,
so a client can tell "this field is wrong" from "these details are wrong" by
status alone.

Two properties of a failed login are deliberate, and both are covered by tests —
do not simplify them away:

- the message never reveals whether the email or the password was wrong;
- an unknown email costs the same time as a wrong password.
  `LoginService::TIMING_EQUALISER` exists purely so a missing user still pays for
  one `Hash::check`. Without it, response timing tells an attacker which
  addresses have accounts.

The login route is throttled. Keep a throttle on anything that rewards guessing.

Sanctum stores a hash of the token; the plain value is returned once and never
again. Tokens do not expire unless `config/sanctum.php` says so.

### The authenticated endpoints

```
POST /api/auth/logout   -> {message, data: null}
GET  /api/auth/me       -> {message, data: {id, name, surname, email, ...}}
```

Both sit in the one `auth:sanctum` group in `routes/api.php`. Keep new
authenticated routes inside that group rather than repeating the middleware —
it is the only thing standing between a token-only endpoint and an open one.

`logout` revokes **only the token the request arrived with**
(`$request->user()->currentAccessToken()`), because one account can be signed in
on several devices and each holds its own token. Revoking every token is the
password reset flow's job, where losing the old sessions is the point.

`me` exists so the SPA can restore a session from a stored token without a
second login. It reuses `ShowUserService`, so it reads the row fresh instead of
echoing whatever the guard resolved.

The whole `users` resource sits in that group too. What is left outside it is
exactly the set of endpoints a caller reaches before they hold a token: login,
activation, forgot-password and reset-password — and each of those is throttled.

`UsersRequireAuthenticationTest` asserts 401 on every users endpoint, for a
missing token, an unknown token and a revoked one, and that a rejected write
leaves the database and the mail queue untouched. It is the only test that would
notice a route slipping out of the group, because every other users test
authenticates in `setUp`.

### Account activation

A created account has a random password nobody knows. The user gets there
through the emailed link:

```
POST /api/auth/activate-account   {email, token, password, password_confirmation}
-> {message, data: {user: {...}, token: "..."}}
```

`Password::broker()->reset()` does the verifying: it checks the token against its
stored hash and deletes it once used, so a link works exactly once. Do not
hand-roll that check. A link that is expired, spent, or not that user's throws
`InvalidActivationTokenException` (422) with a message that does not say which.

Activation sets `email_verified_at` and returns a bearer token, so the SPA does
not have to bounce the user through the login form afterwards.

The password policy lives in the request classes: at least 8 characters, mixed
case, a number, a symbol, and `uncompromised()`. That last rule calls
haveibeenpwned over the network — **tests must stub `UncompromisedVerifier`**,
never let the suite reach out.

### Forgotten passwords

```
POST /api/auth/forgot-password   {email}          -> always the same 200
POST /api/auth/reset-password    {email, token, password, password_confirmation}
                                                  -> {message, data: {user, token}}
```

`forgot-password` answers **identically** whether the address is registered,
unregistered, or belongs to an account that was never activated — and never
throws. Anything else lets an anonymous caller harvest accounts. A test asserts
the three responses are byte-identical; do not "improve" the error handling here.

Both flows mint tokens through the same broker and land in the same table, so:

- `reset-password` refuses accounts with a null `email_verified_at`, or an
  activation link could be spent there to get a password while skipping
  verification;
- that check runs **before** the broker, because the broker deletes the token
  whether or not the callback does anything — checking later would burn an
  unverified account's activation link on every refused attempt.

The two emails point at different frontend paths (`/auth/activate-account` and
`/auth/reset-password`) so the SPA knows which endpoint to post to.

A successful reset revokes every existing Sanctum token: a reset is how someone
recovers a hijacked account, so older sessions must not survive it.

## Roles and authorisation

Two roles, in `App\Enums\UserRole`: `admin` may do everything the API offers,
`user` may only read. The column is a plain string on `users` with a `user`
default, cast to the enum on the model — so a row created without a role is the
harmless one.

Authorisation is `App\Policies\UserPolicy`, applied by the `can:` middleware in
`routes/api.php`:

```php
Route::apiResource('users', UserController::class)
    ->middlewareFor('index', 'can:viewAny,'.User::class)
    ->middlewareFor('store', 'can:create,'.User::class);
```

**`->middlewareFor(...)`, not `->middleware([...])`.** A keyed array passed to
`middleware()` is flattened and applied to *every* action, so `can:create` would
also guard `index` and a plain user would be refused a list they are allowed to
read. That mistake answers 403 — a status that looks deliberate — so nothing
tells you it happened.

Policy methods take **only the caller**. The routes carry a plain `{id}`, not a
bound model, so the middleware passes the class name and Laravel drops it before
calling the method. That is enough for role checks. A rule that depends on the
row — "a user may edit themselves" — needs the loaded model, so it belongs in
the service with the caller passed in, not here.

`before()` is what makes an administrator omnipotent; the methods below it
describe what a plain user may do. Adding an endpoint means adding a method and
a `can:` middleware, or it is open to every signed-in user.

`store` accepts an optional `role`, so an administrator can create another one.
Changing an existing user's role is its own endpoint:

```
PUT /api/users/{id}/role   {role: "admin"}
```

It is kept off `PUT /users/{id}` on purpose — otherwise every profile edit would
be a request to hand out privileges, and the two carry different rules.

**An administrator cannot change their own role** (403,
`CannotChangeOwnRoleException`). The only self-change available to them is a
demotion, which is almost always a misclick and is the one change they cannot
undo for themselves. Refusing it is also what keeps the last administrator from
disappearing: demoting one takes a second one, who is still an administrator
afterwards, so the count can never reach zero. That is why there is no separate
"last admin" check — do not add one without first checking it is reachable.

A role change does **not** revoke tokens. The policy reads the role out of the
database on every request, so the new role applies from the next one; signing the
user out of a session they are still entitled to would be gratuitous. Both
directions have a test that proves it end to end with one unchanged token.

The seeded account is an administrator. `UserResource` exposes `role`, so the
SPA can hide what it must not offer — the API refuses it either way.

## Switching an account off

An administrator can disable an account without deleting it:

```
POST /api/users/{id}/deactivate   -> {message, data: {..., deactivated_at: "..."}}
POST /api/users/{id}/reactivate   -> {message, data: {..., deactivated_at: null}}
```

`deactivated_at` on `users` is null while the account works. **This is not a soft
delete, and soft deletes are deliberately not used here.** A disabled account has
to stay visible — an administrator cannot switch back on what the list no longer
shows — and a soft-deleted row keeps holding its email address in the unique
index, so the address could never be reused and `restore()` would collide with
whatever took it.

`DeactivateUserService` revokes every token the account holds, inside the same
transaction that stamps the column. Without that, a user with a token that never
expires stays signed in until something else stops them.

**An administrator cannot switch off their own account** (403,
`CannotDeactivateOwnAccountException`) — the same shape as the rule on role
changes, and for the same reason. Only an active administrator reaches the
endpoint, and they are still active once somebody else has been switched off, so
an active administrator always remains. That is the entire guarantee: there is no
"last administrator" count anywhere, and adding one would be dead code.

Five places have to agree that a disabled account is out, and each has a test:

| | |
| --- | --- |
| `EnsureAccountIsActive` | on the `auth:sanctum` group — 403 for a token that survived |
| `LoginService` | 403, **after** the password check |
| `ForgotPasswordService` | skipped silently, same 200 as always |
| `ResetPasswordService` | refused as an invalid token |
| `ActivateAccountService` | refused as an invalid token |
| `ResendActivationService` | 403 — only an administrator reaches it |

The differences in that table are the point, not inconsistency:

- **Login says plainly that the account is off** — but only once the password has
  proved the account is the caller's. Answering before that would tell an
  attacker which addresses exist and which are disabled. A wrong password on a
  disabled account still answers 401, exactly like any other wrong password.
- **The token flows refuse with `InvalidActivationTokenException` /
  `InvalidPasswordResetTokenException`**, not with the deactivated message. Those
  checks run *before* the token is verified — a distinct answer there would let
  anyone holding nothing but an email address probe which accounts are off. They
  also run before the broker, so a refused attempt does not spend the link.
- **`forgot-password` stays byte-identical**, disabled or not. It has a test that
  compares the responses.

Reactivating does not give back the revoked tokens; the user logs in again.

## Profile pictures

A user account can carry an avatar, and the frontend gets it back as a URL:

```
POST   /api/users/{id}/avatar   multipart/form-data, field `avatar`
DELETE /api/users/{id}/avatar
-> {message, data: {..., avatar_url: "http://localhost:8000/storage/avatars/01K3....webp"}}
```

`users.avatar_path` holds the path on the disk, never the URL — a stored URL
would freeze the host that was current when the file went up. `UserResource`
renders it through `User::avatarUrl()`, which answers null when there is none.

**The upload is never what gets stored.** `UpdateUserAvatarService` re-encodes
every file to a square 512x512 WebP at quality 80 through Intervention Image
(`cover()`, so a portrait photo is centre-cropped rather than squashed). A photo
off a phone arrives as several megabytes and lands as a few dozen kilobytes;
without that, every row of the users list would pay for pixels nothing renders.
The GD driver is bound to `ImageManager` in `AppServiceProvider` — Intervention
cannot be autowired, it needs a driver — and `gd` + `exif` are installed in
`docker/Dockerfile` for it.

**This is the one endpoint a plain user may write to, and only for themselves.**
`UserPolicy::changeAvatar` returns true for anyone signed in, because the policy
never sees the target row; which account the caller may touch is settled in
`AuthorizeAvatarChangeService`, shared by both use cases so the rule cannot drift
between them. An administrator may change anyone's; anyone else gets 403
(`CannotChangeAnotherUsersAvatarException`) for somebody else's account.

Order of operations, and the reason for it:

- the file is written **before** the row, and deleted again if the transaction
  throws — otherwise a failed write leaves an image nothing points at;
- the **previous** file is deleted **after** the commit, never before, or a
  failed write would leave the user with a broken image;
- the filename is a ULID, so a URL is not guessable from a user's id and a
  replaced avatar can never be served stale from a cache under the old name.

Removing an avatar an account does not have answers 200 and records nothing —
the request asked for a state the account is already in, the same way
`UpdateUserService` stays quiet when a form is saved untouched.

Files live on the `public` disk (`storage/app/public/avatars`), served straight
by nginx through the `public/storage` symlink — `make storage-link`, which
`make setup` runs. That makes an avatar URL readable by anyone who has it: an
`<img>` tag sends no bearer token, so anything behind `auth:sanctum` could not be
rendered without proxying every avatar through the SPA. Accepted deliberately;
do not put anything else on that disk without deciding the same thing again.

Validation lives in `UpdateUserAvatarRequest`: JPEG, PNG, WebP or GIF, at least
64x64, up to 5 MB. SVG is refused — it is a document that can carry script — and
the formats are listed explicitly rather than left to the `image` rule, which
also accepts AVIF, HEIC and HEIF, formats GD may not have been built to decode.

## The audit trail

Every write to a user account leaves a row in `user_logs`, and an administrator
reads it back:

```
GET /api/users/{id}/logs   -> {message, data: [...], links, meta}
```

```
user.created            actor: admin   new: {name, surname, email, role}
user.updated            actor: admin   old/new: only the fields that moved
user.role_changed       actor: admin   old/new: {role}
user.deactivated        actor: admin   old/new: {deactivated_at}
user.avatar_updated     actor: admin, or the account itself   old/new: {avatar_path}
user.avatar_removed     actor: admin, or the account itself   old/new: {avatar_path}
user.reactivated        actor: admin
user.activation_link_sent  actor: admin   no diff -- nothing on the row changed
user.activated          actor: the account itself
user.password_reset     actor: the account itself, no diff ever
```

**Two user references, never one.** Over user accounts both sides are users:
`user_id` is whose record changed, `actor_id` is who changed it. A single column
stops being readable within months. `actor_id` is null for the system — a
seeder, a console command, the scheduler — which is a statement, not a gap.

**Changed fields only, as jsonb, never a snapshot and never real columns.** A
snapshot answers "how did this look on the 5th" but buries "who changed the
email"; real columns would mean every new field on `users` needs a migration
here too, forever. `event` is the sentence a person reads, the jsonb is the
proof. Note that **jsonb does not preserve key order** — compare with
`assertEquals`, not `assertSame`.

`password` and `remember_token` never reach the trail, in either direction —
`RecordUserLogService::NEVER_RECORDED`, with a test that walks every entry a
whole flow produces. A hash is still the secret's shadow, and this table is read
by more people than `users` is.

**The entry is written inside the same transaction as the change**, never queued.
A trail that can fail while the change succeeds is one nobody trusts, and a
rolled-back change leaves no entry — there is a test for exactly that.

The actor arrives as `App\Data\ActorData` (id, ip, user agent), built by the
controller with `fromRequest()`, `guest()` for the endpoints where nobody is
signed in yet, or `system()` off the request cycle. Services still never call
`auth()` or `request()`. On activation and password reset the service attributes
the entry to the account itself with `$actor->actingAs($user->id)`, because the
id is only known once the token has been verified.

Neither foreign key cascades, so deleting a user fails while their trail exists.
That is deliberate: decide what happens to the history first.

Entries are pruned after `UserLog::RETENTION_DAYS` — **three years**, against six
months for a login attempt. The two answer different questions: "who has been
signing in lately" goes stale in weeks, while "who changed this address, and
when" is asked long after the fact, during a dispute or an audit. That number is
a policy decision, not a technical one; raise it if the business says so.

**Known limit, accepted:** nothing here sees a change made straight in the
database or through `tinker`. Only Postgres triggers would, and they cannot name
an application-level actor. If the trail is ever needed as evidence rather than
as support material, that is the conversation to have.

If a second model needs this, `user_id` becomes `auditable_type` +
`auditable_id`; everything else carries over.

## Login history

Every attempt to sign in lands in `login_attempts`, successful or not:

```
GET /api/users/{id}/logins   -> {message, data: [...], links, meta}
```

```
succeeded             the account signed in
invalid_credentials   wrong password, or an address nobody uses
account_deactivated   right password, switched-off account
```

**A separate table from `user_logs`, on purpose.** An attempt on an address
nobody uses has no account to point at, and making `user_logs.user_id` nullable
to fit it would weaken the one column that trail is built on. Logins also
outnumber data changes by orders of magnitude and are pruned on a different
schedule — mixed together, "who edited this account" drowns in "who signed in".

**Both kinds of failure are recorded as the same outcome.** Keeping "no such
user" apart from "wrong password" would write the very distinction the login
endpoint refuses to reveal into a table somebody will eventually expose. There is
a test asserting the two are indistinguishable.

**Every branch out of `LoginService` writes exactly one row**, including the two
failures. That is not tidiness: an extra insert on one path would put back the
timing difference `TIMING_EQUALISER` exists to remove. A test counts the rows.

An attempt on an unknown address is stored with a null `user_id` — that row is
the only place a sweep across invented addresses is visible. It is deliberately
not reachable through the endpoint above, which answers "what happened to this
account"; querying for sweeps is a different question and needs a different view.

The password never reaches the table — `RecordLoginAttemptService` is never
handed one, so it cannot leak one. `LoginRequest` validates the address as an
email first, so a password typed into the wrong box is a 422 and is never
recorded.

Rows are pruned after `LoginAttempt::RETENTION_DAYS` (180 days). One daily
`model:prune` in `routes/console.php` covers both log tables, run by the
`scheduler` container; how long each is kept lives on its own model, so the
schedule holds no opinion of its own.

Reading it needs the same `viewLogs` ability as the audit trail — same audience,
same sensitivity, and one ability cannot be opened up by accident while the other
stays shut.

## Request logging

One structured JSON line per finished request, on stdout, ready for a Loki
agent to scrape:

```json
{"message":"request","level_name":"INFO","datetime":"2026-08-20T21:14:19+00:00",
 "context":{"request_id":"01M0GG…","method":"PUT","path":"/api/users/12/role",
            "route":"users/{id}/role","route_name":"users.role.update",
            "route_params":{"id":"12"},"body":{"role":"admin"},
            "status":200,"duration_ms":41.3,"bytes":312,"ip":"…",
            "user_id":1,"user_role":"admin"}}
```

`App\Http\Middleware\LogRequests` (appended in `bootstrap/app.php`) plus
`App\Logging\RequestContext`. **This is the one piece of the application that
does not go through a service and a repository** — it is infrastructure, not a
use case, and it touches no database. Do not invent a `RecordRequestLogService`
for it.

The line is written in `terminate()`, after the response has gone back, so
encoding it costs the caller nothing.

**`route` alongside `path`.** `/api/users/12/role` has unbounded cardinality and
cannot be grouped; `users/{id}/role` is what a dashboard aggregates on. In Loki,
keep `request_id`, `user_id` and `path` inside the line — never as labels, or
every request becomes its own stream.

**What never goes in:**

- the response body, ever. `/auth/login` alone would put a working bearer token
  into the log stream; only `status`, `bytes` and `duration_ms` are kept;
- anything posted to `api/auth/*` — the body is dropped whole, because all of it
  is either a credential or a token;
- `password`, `password_confirmation`, `current_password`, `token`, `secret` and
  friends anywhere else, at any depth — replaced with `[redacted]`, the key kept
  so the shape stays readable (`RequestContext::REDACTED`);
- uploaded file contents; only name and size;
- a body over 8 KB — dropped, with `body_truncated` and `body_bytes` instead.

A validation failure records **only the rejected field names**, never their
values. Other failures record the exception class, message and location.

**`request_id`** comes from an incoming `X-Request-Id` when a proxy set one, and
is otherwise a ULID. It goes back in the response header so a user can quote it,
and into `Context`, which is what puts it on *every* other log line of that
request — an uncaught exception's stack trace included, and the logs of any
queued job the request dispatches, because Laravel carries the context into the
queue.

Levels: 5xx is `error`, 401/403/429 are `warning` (one is unremarkable, a
thousand from one address is not), everything else `info`. Slower than 1s adds
`slow: true`.

`/up`, `horizon/api/*`, `pulse*` and every `OPTIONS` are skipped — constant
traffic that says nothing.

**The logger can never break a request.** `terminate()` catches everything and
degrades to one `warning` line; there is a test that binds a logger which throws
and asserts the response is unaffected.

`LOG_CHANNEL=stderr` with `LOG_STDERR_FORMATTER=Monolog\Formatter\JsonFormatter`,
so *all* application logs are JSON on stderr, not just this one. The official
php-fpm image sets `catch_workers_output = yes` and `decorate_workers_output =
no`, so those lines reach `docker compose logs php` unchanged and a Loki agent
can read them straight. `storage/logs/laravel.log` is no longer where anything
is looked for.

`phpunit.xml` sets `LOG_CHANNEL=null`, or every test would print its own line and
bury the results. `MessageLogged` still fires, which is what the logging tests
assert on.

This is operational, not evidence: high volume, short retention, and it lives
outside the database. It is not the audit trail and must not be used as one.

### Logging from a service or a job

Write the event, not the sentence: the message is a stable, low-cardinality
string and everything variable goes in the context array.

```php
Log::info('activation link queued', ['user_id' => $user->id]);
```

`Log::info("sent link to user {$id}")` cannot be grouped, counted or alerted on
— every line is a new string. Same rule as `route` beside `path`.

Log **decisions and outcomes**, never steps. "Entered the method" is volume
nobody reads. Worth a line: a job dispatched, an email skipped and why, a third
party that answered 503, a fallback that fired.

Do not duplicate `user_logs`. The audit trail answers "who changed what" and is
evidence; the application log answers "what did the system do and why" and is
operational. `user.role_changed` belongs in the first and nowhere else; "SMTP
refused the message" belongs in the second.

**Jobs get the same treatment as requests, automatically.** `App\Logging\JobLogger`
listens to the queue events (registered in `AppServiceProvider`) and:

- puts `job`, `job_id`, `queue`, `connection` and `attempt` into `Context`, so
  every line the job writes carries them without the job's author doing anything;
- writes one `"message":"job"` line when the work ends, with `outcome` and
  `duration_ms` — `info` when processed, `error` when it failed for good;
- writes `"message":"job attempt failed"` at `warning` for each attempt that
  threw. A job that eventually fails produces both, which is what happened.

`request_id` needs no help: Laravel packs the context into the job payload when
it is queued and hydrates it in the worker, so a job dispatched by a request is
already logging under that request's id — in the `horizon` container, minutes
later. Verified end to end: one `X-Request-Id` on `POST /api/users` shows up on
the request line in the `php` container and on the notification's job line in
`horizon`.

`resolveName()` reports the notification or listener a person would recognise
(`App\Notifications\AccountCreatedNotification`), not Illuminate's
`SendQueuedNotifications` wrapper.

**Secrets are stripped below all of this.** `App\Logging\RedactProcessor` is a
Monolog processor on the channel, so it runs on *every* record — a hand-written
`Log::info('reset', ['token' => $token])` included — and shares
`RequestContext::REDACTED` with the request logger. One list, or the weaker of
the two becomes the one that leaks.

`LOG_LEVEL=debug` suits local work. Production belongs on `info`, or every
`Log::debug` in every package is paid for by the gigabyte.

## Demo data

`DemoDataSeeder` (`make demo`) fills the database with eight accounts and about
six weeks of history — every state the screens have to render: a never-activated
account, a switched-off one, a promotion, a renamed user, failed logins and a
handful of attempts on addresses nobody uses.

Half the accounts have an avatar and half deliberately do not, so the frontend
has to draw its placeholder as well as an image. The pictures are generated —
a head and shoulders on a flat colour, drawn 900x600 so the crop has something
to do — and they go in through `UpdateUserAvatarService` like any upload, which
is also what puts `user.avatar_updated` in the trail: by the account itself for
most, by an administrator for one.

It is deliberately **not** in `DatabaseSeeder`: a fresh install gets one real
administrator and nothing else.

Entries go in through `RecordUserLogService`, `RecordLoginAttemptService` and
`UpdateUserAvatarService`, the same services the application uses, so demo
history cannot drift from real history. Only the clock is faked: each write runs inside `Carbon::setTestNow()`,
which is what backdates `created_at`, `deactivated_at` and the login timestamps
without writing any dates by hand.

Re-running changes nothing — it stops when it finds the account it created last
time.

## The frontend contract

`docs/frontend-integration.md` is the step-by-step guide the separate SPA (and
whoever works on it) follows: the envelope, the token flow, the two pages that
emailed links point at, the status codes and what the client should do about
each.

**It is part of the API contract, not a leftover note.** Changing a response
shape, adding a required field or moving a status code means editing that file
in the same change. `/docs/api` is generated and cannot go stale; this one can.

## Looking at the logs

`compose.observability.yaml` runs Loki, Alloy and Grafana as **its own compose
project**, started with `make observe` and reachable at `localhost:3000` with a
provisioned dashboard. `make up` does not start it and `make down` cannot stop
it by accident.

It is deliberately not part of `compose.yaml`: the development loop stays fast,
a second service could not live inside the first one's stack, and production
does not deploy Grafana as part of the application either. The only coupling is
the Docker socket — Alloy reads every container's stdout and takes the compose
service name as a label — so the folder moves to its own repository later with
no change to the application.

Alloy, not Promtail: Grafana took Promtail to end of life in March 2026.

Labels in Loki are `service`, `project`, `container`, `env` and `level` only.
`request_id`, `user_id` and `route` stay inside the line — a label with many
values gives Loki one stream per value.

Prometheus and Tempo are deliberately absent. The error rate and p95 per route
come out of `context_status` and `context_duration_ms` in the lines already
written; Prometheus earns its place when something is needed that logs cannot
carry, and Tempo when there is more than one service.

**Dev only, and not close to production-shaped**: Grafana has no password, Loki
has no retention, nothing is encrypted. `docker/observability/README.md` is the
usage guide — how to trace one request, the LogQL for the usual questions, and
what the field names become after `| json`.

## API documentation

Generated by **Scramble** from the code itself — there are no annotations to
keep in sync. It reads route definitions, FormRequests, API Resources and return
types.

| | |
| --- | --- |
| UI | `/docs/api` |
| OpenAPI spec | `/docs/api.json` |
| Config | `config/scramble.php` |

Routes under `api/*` are picked up automatically. A controller's docblock
becomes the endpoint's summary and description:

```php
/**
 * List users
 *
 * Returns every user, ordered by name and then surname. Not paginated.
 */
public function __invoke(): JsonResponse
```

**`App\Support\Scramble\ApiResponseTypeInfer` is what makes this work.**
Scramble reads the controller's return statement, and `ApiResponse::success(...)`
is a static call it cannot see through — without the extension every endpoint
documents as an empty `object`. The extension maps each `ApiResponse` method to
the `{message, data}` shape and its status code.

So: **adding a method to `ApiResponse` means adding it to that extension's
`match`**, otherwise endpoints using it silently lose their response schema. It
is registered in the `extensions` array of `config/scramble.php`.

Write resource properties with real types (`?->toIso8601String()`, typed
`toArray()` return) — that is what Scramble turns into the schema.

`security_strategy` in `config/scramble.php` is set to
`MiddlewareAuthSecurityStrategy`. It reads `auth`/`auth:*` off the routes,
declares the bearer scheme once, and marks every other route `security: []` —
that is what puts the Authorize box in the docs UI. Without it a protected
endpoint documents as public and cannot be tried from the page.

Scramble derives error responses from the exceptions a service throws, so an
endpoint can document a status it cannot actually return. `#[IgnoreResponse(404)]`
on `AuthController::me` removes one: the service throws it, but a token only
resolves while its owner exists. Use the attribute only when the status is
genuinely unreachable, never to tidy up one that is merely rare.

## There is no frontend build step

**This overrides the Boost "Frontend Bundling" and "Vite Error" sections below.**

Vite, Tailwind, npm and `node_modules` were removed from this project. There is
no `package.json`, no build, and no Node container. The application frontend is
a separate project; what lives here is one Blade page at `/` linking to API
docs, Horizon and Pulse.

Styling for that page is hand-written CSS served straight from
`public/css/app.css` and linked with `asset()`. Add CSS there. Never add `@vite`
to a view, never reintroduce npm to serve a page.

Horizon and Pulse inline their own compiled assets, so they need nothing from
this project. If a Pulse or Horizon page looks unstyled, the cause is the
package's own published assets, not a missing build.

If a real frontend toolchain is ever needed here again, that is a decision to
raise with the user first — not something to reinstall to fix a styling issue.

## Running tests

**This overrides the "Running Tests" rules in the Boost PHPUnit section below.**

Tests run against a real PostgreSQL server (the `postgres-test` container), not
SQLite — `phpunit.xml` sets `DB_CONNECTION=pgsql` and `DB_HOST=postgres-test`.
That keeps the test suite on the same driver as dev and production, so
Postgres-specific behaviour (jsonb, case sensitivity, strict types) is actually
exercised instead of being hidden by SQLite.

| Goal | Command |
| --- | --- |
| Whole suite | `make test` |
| One file | `make artisan c="test --compact tests/Feature/ExampleTest.php"` |
| One test | `make artisan c="test --compact --filter=testName"` |

`make test` waits for `postgres-test` to accept connections before starting.
When filtering with `make artisan`, make sure the stack is already up.

Tests that call a protected endpoint authenticate with `$this->actingAsUser()`
or `$this->actingAsAdmin()` from `Tests\TestCase`, normally once in `setUp` —
the plain user may only read, so a test that writes needs the admin helper. The
caller they sign in with is **not** saved to the database: nothing reads anything
off it, and a real row would show up in the lists, counts and orderings the users
tests assert on. **Pass a user in when the endpoint reads anything off the
caller** — `PUT /users/{id}/role` compares ids, and an unsaved caller has a null
one, which fails as a TypeError rather than as an assertion.

Do not hard code an id in a URL. Postgres sequences are not rolled back with the
transaction `RefreshDatabase` wraps each test in, so ids keep climbing across the
suite and `/api/users/1` becomes a 404 somewhere in the middle of a run.

`postgres-test` is a separate server from `postgres`, so `RefreshDatabase` and
`migrate:fresh` in a test can never reach dev data. Its storage is tmpfs, so
restarting the container wipes it and the next run rebuilds the schema — that is
expected, not a failure.

## Deploying to production

One command on the server, after `git pull`:

```
./deploy.sh          # or: make deploy
```

Images are built **on the server** — there is no registry and nothing to push.

The from-zero runbook covering all three repositories in order — laptop-side
prep, server preparation, DNS, infra, this API, the frontend, backups, and how a
fourth service is added — lives in the **infra repository**, at
`docs/server-setup.md`. It is there rather than here because it describes the
whole machine, not this application.

`docs/deployment.md` is the deeper reference for this repository's half. The
SPA's own repository documents its deploy; what this side owes it is
`docs/frontend-integration.md`, the API contract. Like `docs/frontend-integration.md` it is part of the contract,
not a leftover note — a change to the deploy flow means editing it in the same
commit.

### What this repository owns, and what it does not

`compose.prod.yaml` holds nginx, php, horizon, scheduler, postgres and redis:
the application and everything **only** the application uses. Its Postgres is
versioned with the migrations that shape it, which is exactly why it belongs
here.

**Traefik and the log stack are not here.** They route and observe every service
on the server, the frontend included, so they live in their own repository and
are deployed once per machine. `deploy.sh` only *checks* that the `edge` network
exists and fails with instructions if it does not — a `git pull` in this
repository must not be able to change routing for something it is not
responsible for.

The dividing line is not "infrastructure versus application". It is: shared by
more than one application, or used by exactly one. See `docs/deployment.md`.

**`compose.prod.yaml` is standalone, not an override of `compose.yaml`.**
`-f compose.yaml -f compose.prod.yaml` would merge them, and compose merges
sequences by appending: the development bind mount `.:/var/www/html` would
survive into production and shadow the code baked into the image. Nothing would
fail — you would simply be running whatever sits on the server's disk.

### Two nginxes, and only one of them is here

The `web` image is the **application's** nginx: it serves `public/` and speaks
FastCGI to php-fpm, and `public/` is copied into it from the `app` stage. That
is what makes it work without a bind mount.

Traefik is the **edge**: TLS, domains, routing between services. It does not
serve files at all, which is why the application still needs its own nginx and
why the frontend will need one too.

`fastcgi_pass` in `docker/nginx/prod.conf` goes through a variable and a
`resolver`, deliberately. With a literal `fastcgi_pass php:9000` nginx resolves
the name once at startup and **refuses to start** if php is not up yet — one
crashed container then keeps the web server down with it and a restart loop
never recovers. As a variable the name is resolved per request.

### The image

`docker/Dockerfile`, five stages: `base` (runtime + extensions, shared with
dev), `dev`, `vendor` (`composer install --no-dev`), `app`, `web`.

`route:cache`, `view:cache` and `event:cache` run at **build** time — nothing in
them depends on the environment. `config:cache` runs at **container start**, in
`docker/php/entrypoint.sh`, because its values come from the server's
environment: caching it at build would bake production credentials into the
image and make every later edit to `.env.production` do nothing.

The entrypoint drops from root to `www-data` for every command **except
`php-fpm`**, whose master must stay root — the base image points `error_log` at
`/proc/self/fd/2` and only root can reopen the container's stderr. Its workers
drop through `user =` in `docker/php/www.prod.conf`. Dropping first looks tidier
and makes fpm exit 78 on every start.

`php`, `horizon` and `scheduler` are the same image with different commands,
exactly as in development.

### What deploy.sh guarantees

- **Preflight refuses to start** on an incomplete environment: missing
  `APP_KEY`, `APP_ENV` that is not `production`, a short database or Redis
  password, and above all `APP_DEBUG=true` — which serves a stack trace listing
  every environment variable to whoever triggers an error.
- **The database is dumped before migrations run**, never after
  (`docker/scripts/backup.sh`). A failed dump aborts the deploy.
- **Maintenance mode is held in Redis** (`APP_MAINTENANCE_DRIVER=cache`), so one
  container switching it on takes all four out of service. A file-based flag
  lives inside one container's filesystem and would leave the other three
  serving traffic. The exit trap lifts it whatever else went wrong.
- **Health is proven, not assumed**: it polls `/up` through nginx and does not
  report success until it answers.
- **The previous images are kept as `:previous`** — `make rollback` puts them
  back. It does not revert migrations; that is what the dump is for.

### The first administrator

`admin:create` is the only way to create the account that has to exist before
anybody can call `POST /api/users`:

```
make prod-artisan c="admin:create ops@example.com --name=Uroš --surname=Minić"
```

**`DatabaseSeeder` cannot do this in production.** `UserSeeder` goes through
`User::factory()`, factories need Faker, and Faker is a require-dev package that
`composer install --no-dev` leaves out of the image. Even if it were there it
would create an account whose password is the word `password`.

The command goes through `StoreUserService` like every other creation, so the
account gets the random password, the emailed activation link and both audit
trail entries, with `ActorData::system()` as the actor. `--show-link` prints the
activation URL for a server whose SMTP is not working yet — and says out loud
that doing so invalidates the link that was just emailed, because minting a
token deletes the previous one.

### Secrets and state

`.env.production` is gitignored and dockerignored. It is read twice — by compose
for `${API_DOMAIN}` interpolation, and by the containers as their environment —
and never enters an image. **No `${VAR}` references inside it**: compose passes
those lines through verbatim, so `APP_URL=https://${API_DOMAIN}` arrives at PHP
as that literal string.

Avatars live in the `storage-public` named volume, mounted into php read-write
and into nginx read-only. It is the only application state outside Postgres and
the one thing a redeploy would otherwise destroy — back it up with the database.

Not one service in `compose.prod.yaml` publishes a port. Postgres and Redis are
reachable only from the internal network (`make prod-psql` goes through
`docker exec`). The network is deliberately **not** `internal: true`: the
application needs outbound access for SMTP and for the haveibeenpwned lookup
that `uncompromised()` performs on every password rule.

### Behind a proxy

`bootstrap/app.php` trusts the forwarded headers (`trustProxies(at: '*')`).
Without it `$request->ip()` is Traefik's container address, and that is the
value `user_logs`, `login_attempts` and the request log all record — every row
would say the same thing about a different person, and nothing would fail.
`TrustedProxyTest` covers it.

`at: '*'` rather than a fixed CIDR because the proxy's address is handed out by
Docker and changes when the network is recreated. It is safe **only** because
nothing publishes a port: the sole route to PHP is through the proxy.

### Horizon, Pulse and Grafana

All three read production data — queued job payloads with email addresses, slow
queries with their bindings, every request line. All three are locked:

- `viewHorizon` and `viewPulse` are defined against `UserRole::Admin`
  (`HorizonServiceProvider`, `AppServiceProvider`). The defaults they replace
  were "an empty list of emails" and "local environment only", which is shut
  rather than protected. Covered by `OperationalDashboardsTest`.
- Grafana has a real password, no anonymous access and no sign-up, and reaches
  the internet only through Traefik on its own subdomain.

Loki deletes lines after `LOKI_RETENTION_PERIOD` (30 days by default). The dev
observability stack has none of this — see the notes in each file.

## Configuration facts worth knowing

- Database is PostgreSQL 18. The Compose volume must mount
  `/var/lib/postgresql`, **not** the pre-18 `/var/lib/postgresql/data` — the old
  path still boots but silently persists nothing.
- Cache and queue are Redis. Cache is on `REDIS_CACHE_DB=1` and queue/Horizon on
  `REDIS_DB=0`, so `cache:clear` cannot drop queued jobs. Sessions are in Postgres.
- Queues are processed by the `horizon` container, not by `queue:work`. After
  changing job code, run `make horizon-restart`.
- Scheduled tasks are run by the `scheduler` container (`php artisan
  schedule:work`), not by a crontab. It re-reads `routes/console.php` on every
  tick because each run is a fresh process, so a schedule change needs no
  restart — unlike Horizon, which holds the application in memory. Times are in
  `APP_TIMEZONE`, which is UTC, so `daily()` fires at 00:00 UTC.
- Host ports come from `.env` (`APP_PORT`, `FORWARD_DB_PORT`, ...). Postgres is
  published on **5433** and the test database on **5434**, because 5432 is
  commonly taken by a local Postgres install.
- Deeper rules live in `.ai/rules/` — read the ones whose globs match what you
  are editing.

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
