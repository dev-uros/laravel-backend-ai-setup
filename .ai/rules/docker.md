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

`config:cache` runs at container start, never at build (env only exists on the server). `route:cache`/`view:cache`/`event:cache` run at build.

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

No service in `compose.prod.yaml` publishes a port; `make prod-psql` goes through `docker exec`. The network is deliberately not `internal: true` — the app needs outbound access for SMTP and for the haveibeenpwned lookup `uncompromised()` performs on every password rule.

The `storage-public` named volume (php read-write, nginx read-only) holds the avatars. It is the only application state outside Postgres and the one thing a redeploy would otherwise destroy — back it up with the database.
