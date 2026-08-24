---
paths:
  - '{app/Console/**,routes/console.php}'
---

# Console

## admin:create is the only way to make the first administrator
`admin:create` exists because somebody has to hold a token before anybody can call `POST /api/users`, and nothing else in the codebase can produce that account in production.

    make prod-artisan c="admin:create ops@example.com --name=Uroš --surname=Minić"

`DatabaseSeeder` cannot do this: `UserSeeder` goes through `User::factory()`, factories need Faker, and Faker is a require-dev package that `composer install --no-dev` leaves out of the image. Even if it were there, it would create an account whose password is the word `password`.

The command goes through `StoreUserService` like every other creation, so the account gets the random password, the emailed activation link and both audit entries, with `ActorData::system()` as the actor. `--show-link` prints the activation URL for a server whose SMTP is not working yet — and says out loud that using it invalidates the link that was just emailed, because minting a token deletes the previous one.

A console command is a controller for a different transport: it parses input, calls one service, and prints. Business logic goes in the service, never in `handle()`, and `ActorData::system()` is the actor whenever there is no request.

## The schedule holds no opinions of its own
`routes/console.php` runs one daily `model:prune` listing both log models. How long each is kept lives on the model (`LoginAttempt::RETENTION_DAYS`, `UserLog::RETENTION_DAYS`) — never inline in the schedule. The `scheduler` container runs it; times are in `APP_TIMEZONE` (UTC), so `daily()` fires at 00:00 UTC.
