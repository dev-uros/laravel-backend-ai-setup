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

Postgres, Redis and the app's nginx DO belong here: only this application uses them, and its Postgres is versioned with the migrations that shape it. The dividing line is "shared by more than one application" vs "used by exactly one", not "infrastructure vs application code".

The dev-only `compose.observability.yaml` stays in this repo on purpose: on a laptop there is no second service to share it with.
