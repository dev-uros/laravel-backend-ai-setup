---
paths:
  - '{compose.yaml,docker/**}'
  - '{Makefile,compose.yaml,.env.example}'
  - '{phpunit.xml,tests/**}'
  - '{compose.yaml,routes/console.php,Makefile}'
---

# General

## Postgres 18 volume must mount /var/lib/postgresql, not /data
PostgreSQL 18+ changed the image's PGDATA to a version-specific path (/var/lib/postgresql/18/docker) and moved the declared VOLUME to /var/lib/postgresql.

Mounting the pre-18 /var/lib/postgresql/data still starts fine but silently persists nothing — the data lives outside the mount and vanishes on `docker compose down`. compose.yaml therefore mounts postgres-data:/var/lib/postgresql and sets PGDATA explicitly.

On a major upgrade (19.x) the path becomes /var/lib/postgresql/19/docker; the mount point stays the same.

## Run artisan/composer/npm inside the containers via make
The app runs in Docker: nginx -> php-fpm, plus a separate horizon worker, postgres, redis and a vite/node container.

Use the make targets (`make artisan c="..."`, `make composer c="..."`, `make npm c="..."`, `make php`) rather than running php/composer on the host. DB_HOST=postgres and REDIS_HOST=redis only resolve inside the compose network, so host-side artisan commands that touch the DB or cache will fail.

make exports WWWUSER/WWWGROUP from `id -u`/`id -g` and execs as www-data (remapped to that uid at build time) so files written into the bind mount stay owned by the host user. Bypassing make and exec'ing as root will leave root-owned files on Linux.

## Tests run on Postgres, not SQLite
phpunit.xml points DB_* at the `postgres-test` compose service (laravel_testing), not SQLite :memory:, so tests exercise the same driver as dev and production — jsonb, case sensitivity, strict types.

It is a separate Postgres server from the dev one, so migrate:fresh / RefreshDatabase in a test can never reach dev data. Its storage is tmpfs: restarting the container wipes it and the next run rebuilds the schema.

Tests must be run inside the containers (`make test`) — DB_HOST=postgres-test only resolves on the compose network, so running phpunit on the host will fail to connect.

## Scheduled tasks run in the scheduler container
`compose.yaml` has a `scheduler` service running `php artisan schedule:work` — the container equivalent of Laravel's one crontab line. Anything registered in `routes/console.php` runs there; nothing else runs the scheduler.

Each tick spawns a fresh `schedule:run`, so a change to `routes/console.php` takes effect on the next minute without restarting the container. That is the opposite of `horizon`, which holds the application in memory and needs `make horizon-restart` after a code change.

It reuses the same image as `php` and `horizon` and runs as www-data, so files it writes into the bind mount stay owned by the host user.

Times are in APP_TIMEZONE (UTC), so `daily()` fires at 00:00 UTC, not local midnight.

To verify the loop actually fires rather than just that the container is up, add a temporary `->everyMinute()` task, watch `docker compose logs scheduler` across a minute boundary, then remove it.

## Redis database split, sessions, and host ports
Cache is on `REDIS_CACHE_DB=1` and the queue/Horizon on `REDIS_DB=0`, so `cache:clear` cannot drop queued jobs. Sessions are in Postgres, not Redis.

Host ports come from `.env` (`APP_PORT`, `FORWARD_DB_PORT`, ...). Postgres is published on **5433** and the test database on **5434**, because 5432 is commonly taken by a local Postgres install.

Queues are processed by the `horizon` container, never `queue:work`. Horizon holds the application in memory, so after changing job or notification code run `make horizon-restart` — unlike the scheduler, which re-reads `routes/console.php` every tick.
