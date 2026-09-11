---
paths:
  - '{docker/**,compose*.yaml,deploy.sh}'
---

# Docker

## Production Docker: one Dockerfile, standalone prod compose, deploy.sh only
`docker/Dockerfile` holds every stage (base, dev, vendor, app, web). The extension list lives once in `base` -- never add a second Dockerfile for prod, that is how dev and prod drift.

`compose.prod.yaml` is standalone, NOT an override of `compose.yaml`. Merging them with `-f a -f b` appends sequences, so the dev bind mount `.:/var/www/html` would survive into production and shadow the baked-in code. Nothing fails; you just run whatever is on the server's disk.

Deploy only with `./deploy.sh` (`make deploy`) -- a bare `up -d` skips the preflight, the pre-migration database dump, maintenance mode and the health gate.

Two traps that cost real debugging:
- `docker/php/entrypoint.sh` must NOT drop privileges for `php-fpm`; its master needs root to reopen `/proc/self/fd/2` or fpm exits 78 on every start. Workers drop via `user =` in `www.prod.conf`. Everything else (horizon, scheduler, artisan) is dropped to www-data.
- `fastcgi_pass` in `docker/nginx/prod.conf` goes through a variable plus `resolver 127.0.0.11`. A literal `fastcgi_pass php:9000` resolves once at startup and nginx refuses to start when php is not up yet.

`config:cache` **and `route:cache`** run at container start, never at build (env only exists on the server); `view:cache`/`event:cache` run at build. Routes moved to the entrypoint because Livewire v4 -- which the Horizon and Pulse dashboards run on -- derives its endpoint from the application key, `substr(hash('sha256', config('app.key').'livewire-endpoint'), 0, 8)`, so a build-time route cache carries the hash of an empty key while the rendered page carries the hash of the real one, and every Livewire POST 404s. **Before caching anything at build time, check whether its output depends on the environment**; a route file is no longer safe to assume it does not.

## This repo owns the app, not the proxy: edge network is external
Traefik and the Loki/Alloy/Grafana stack live in a SEPARATE `infra` repository, deployed once per server (`/opt/infra`, `make up`). They route and observe every service on the box, including the frontend.

`deploy.sh` only checks that the `edge` network exists and fails with instructions. Never add a `docker network create edge` or a `docker compose up` on a proxy here -- one `git pull` in this repo must not change routing for a service it is not responsible for.

The `web` image is the **application's** nginx: it serves `public/` (copied in from the `app` stage, which is what makes it work with no bind mount) and speaks FastCGI to php-fpm. Traefik is the **edge**: TLS, domains, routing between services, and it serves no files at all. That is why the application still needs its own nginx, and why the frontend will need one too.

Postgres, Redis and the app's nginx DO belong here: only this application uses them, and its Postgres is versioned with the migrations that shape it. The dividing line is "shared by more than one application" vs "used by exactly one", not "infrastructure vs application code".

The dev-only `compose.observability.yaml` stays in this repo on purpose: on a laptop there is no second service to share it with.

## What deploy.sh guarantees, and what .env.production may not contain
`deploy.sh` is not a wrapper around `up -d`; each step is there because its absence has a specific failure:

- **Preflight refuses to start** on a missing `APP_KEY`, an `APP_ENV` that is not `production`, a short database or Redis password, and above all `APP_DEBUG=true` — which serves a stack trace listing every environment variable to whoever triggers an error.
- **The database is dumped before migrations run**, never after (`docker/scripts/backup.sh`). A failed dump aborts the deploy.
- **Maintenance mode is held in Redis** (`APP_MAINTENANCE_DRIVER=cache`), so one container switching it on takes all four out of service. A file flag lives inside one container's filesystem and leaves the other three serving traffic. The exit trap lifts it whatever else went wrong.
- **Health is proven**: it polls `/up` through nginx and does not report success until it answers.
- **Previous images are kept as `:previous`**; `make rollback` puts them back. It does not revert migrations — that is what the dump is for.

`.env.production` is gitignored and dockerignored, read twice (by compose for `${API_DOMAIN}` interpolation, and by the containers as their environment), and never enters an image. **No `${VAR}` references inside it**: compose passes those lines through verbatim, so `APP_URL=https://${API_DOMAIN}` arrives at PHP as that literal string.

Only Postgres publishes a port in `compose.prod.yaml`, and only as `127.0.0.1:${DB_HOST_PORT:-5432}:5432` — the loopback prefix is the whole of what makes it safe, since a bare `5432:5432` binds 0.0.0.0 and puts the database on the public IP. It exists so a client (Beekeeper, DataGrip) can reach it over an SSH tunnel against a stable address; forwarding to the container's own IP works too but Docker reassigns that whenever the network is recreated. Nothing else publishes anything, and `make prod-psql` still goes through `docker exec`. The network is deliberately not `internal: true` — the app needs outbound access for SMTP and for the haveibeenpwned lookup `uncompromised()` performs on every password rule.

The `storage-public` named volume (php read-write, nginx read-only) holds the avatars. It is the only application state outside Postgres and the one thing a redeploy would otherwise destroy — back it up with the database.

Dev hides one class of bug here completely: `compose.yaml` bind-mounts `.:/var/www/html` into all four services, so php, horizon and scheduler share one filesystem no matter what. In production they do not. A disk with no named volume is **container-local**, and the failure is silent and asymmetric — php writes an upload in the request, horizon looks for it in its own copy and does not find it. Before adding a disk to `config/filesystems.php`, add its volume here, and put the mount on the `x-app` anchor so all three application services get it, not on one service.

## `init: true` is not optional: schedule:work leaks zombies
`php artisan schedule:work` spawns a child per due task and never reaps it. Measured on the development stack: **913 zombies in four days**, and a production stack runs for months between restarts. php and horizon do **not** leak -- the php-fpm master and the Horizon master reap their own children -- so the anchor carries `init: true` for the one service that needs it and two that do not care.

Do not "harden" this with `pids_limit` instead. Without an init process a limit turns a leak that takes months into a hard failure in two days; with one it is a backstop that was never reached. If you add it, add it after `init: true`, never as a substitute.

## The cache and the queue are separate Redis servers
`maxmemory` in Redis is per **instance**, never per database. The queue's server runs `noeviction` so a job can never be silently dropped -- which means a cache sharing that server grows into the same ceiling and starts failing `dispatch()`. Separate logical databases (`REDIS_DB=0`, `REDIS_CACHE_DB=1`) do not help; only a second server does.

So `redis` is the queue (noeviction, appendonly, a volume) and `redis-cache` is the cache (`allkeys-lru`, `--save ""`, `--appendonly no`, **no volume**). A cache that survives a restart buys nothing and costs an fsync on every write.

`config/database.php` reads `REDIS_CACHE_HOST` with a fallback to `REDIS_HOST`, so an environment that sets neither collapses back to one server rather than breaking. `compose.yaml` runs the split too: the arrangement production depends on is the one development exercises. `deploy.sh` starts `redis-cache` alongside `postgres` and `redis`, before `artisan down` -- maintenance mode is written through the cache store, so a deploy that has not started that server dies at the step whose whole purpose is keeping half-migrated pages off the site.

## The scheduler's healthcheck proves firing, not liveness
`schedule:work` dying takes its container with it, and `restart: unless-stopped` brings it back -- a process-existence check is very nearly a tautology, and after `init: true` PID 1 is `docker-init` rather than the command, so `/proc/1/cmdline` checks answer about the wrong process. `pgrep` is not in the image either.

What is worth catching is the loop alive but no longer dispatching. `routes/console.php` touches `storage/framework/schedule-heartbeat` every minute and the healthcheck is a shell test on its age -- no PHP process to boot every 60 seconds. The name matters: `schedule-*` is already ignored in `storage/framework/.gitignore`, and `scheduler-*` is not.

A Docker healthcheck restarts nothing. It makes a stalled scheduler show as unhealthy in `make prod-ps`, which is the difference between noticing in a day and noticing when the disk fills.

## Memory ceilings exist so the kernel does not choose
Every ceiling is `${VAR:-default}` and every default is sized for an 8 GB server: postgres 3g, php and horizon 1536m each, scheduler 512m, nginx 256m, redis 768m, redis-cache 384m. A smaller machine overrides them in its own `.env.production` (see the Sizing section of `.env.production.example`) -- never by editing the defaults, which would follow the image onto every other server. The sum exceeds the box **on purpose** -- these are ceilings, not reservations, and the stack idles near 2 GB. The point is that a leaking worker hits its own ceiling and dies instead of triggering the OOM killer, which picks by `oom_score` and likes long-lived, large processes -- that is, Postgres.

php and horizon get `memswap_limit` equal to `mem_limit`, so a runaway dies cleanly rather than thrashing swap. Postgres gets `oom_score_adj: -500` to be last in line, plus `shm_size: 256mb`: Docker's 64 MB default is what parallel query and large hash joins exhaust, and that fails a query -- not the server -- with `could not resize shared memory segment`, which reads like a random bug.

The four Postgres settings in `command:` (`shared_buffers`, `effective_cache_size`, `work_mem`, `maintenance_work_mem`) are there because the image ships tuned for a far smaller machine; `shared_buffers` defaults to 128 MB. `effective_cache_size` allocates nothing -- it tells the planner how much OS cache to assume, so leaving the 2 GB default on a small box is not a memory problem but a planning one.

## Worker counts are environment, not code
What consumes the memory is the number of PHP processes, not the ceilings above them. `HORIZON_MAX_PROCESSES` is read by `config/horizon.php` through `env()`, which works because the entrypoint runs `config:cache` at container start rather than at build time.

php-fpm and opcache are the exception: FPM does not interpolate variables in a pool file -- it fails to parse one outright -- and php.ini has no such syntax either. So `docker/php/entrypoint.sh` writes `php-fpm.d/zzz-tune.conf` and `conf.d/zzz-tune.ini` from `PHP_FPM_MAX_CHILDREN` and `PHP_OPCACHE_MEMORY`, and only when those are set -- an unset environment leaves the files baked into the image untouched. `zzz-` because both directories are read alphabetically.

The spare-server values are derived from `PHP_FPM_MAX_CHILDREN` rather than configured separately: php-fpm refuses to start when min/max spare exceed max_children, so a lowered worker count with the image's original 2/6 spares produces a container that will not boot and says only `failed to post process the configuration`.

## The caller's address is resolved once, in Laravel
`docker/nginx/prod.conf` carries **no** `set_real_ip_from` / `real_ip_header`, and `bootstrap/app.php` trusts the **private ranges**, not `'*'`. Both halves matter:

- nginx rewriting `REMOTE_ADDR` to the caller's public address makes Laravel stop trusting the connection, so it disbelieves `X-Forwarded-Proto: https` and emits `http://` links on an https site -- which browsers then block as mixed active content, and the Pulse dashboard sits in skeletons with nothing in any log.
- `at: '*'` declares the whole internet a trusted proxy, and Laravel Sentinel refuses a `local` request that has a public address and arrives through a trusted proxy -- that is Horizon and Pulse locked out.

Nothing in nginx needs the address: there is no `limit_req` (rate limiting is Traefik's) and the access log that matters is the JSON line `LogRequests` writes, which reads it from Laravel.
