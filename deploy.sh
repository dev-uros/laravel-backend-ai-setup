#!/usr/bin/env bash
#
# One command, whole stack. Run it on the server after `git pull`:
#
#   ./deploy.sh          (or: make deploy)
#
# Order matters, and every step here exists because skipping it breaks
# something that is hard to see afterwards:
#
#   1. refuse to start unless the environment is complete and sane
#   2. check the shared `edge` network exists -- this repo does not create it
#   3. build the images -- the previous ones are kept as :previous
#   4. start Postgres and Redis and wait for them to be healthy
#   5. dump the database BEFORE any migration touches it
#   6. maintenance mode on, migrate, roll the containers, maintenance off
#   7. do not report success until /up actually answers
#
# It is safe to run twice. It does not delete data. If it fails it says where,
# leaves the previous images in place, and takes the site out of maintenance.

set -Eeuo pipefail

cd "$(dirname "$0")"

ENV_FILE=.env.production

APP_DC=(docker compose -f compose.prod.yaml --env-file "$ENV_FILE")

MAINTENANCE_ON=0
# Nothing has been changed yet. The exit handler uses these to decide whether a
# failure is worth dumping logs and offering a rollback for -- a deploy that
# fell over in preflight has touched nothing, and saying otherwise sends you
# looking for a problem that is not there.
STACK_TOUCHED=0
ROLLBACK_AVAILABLE=0

# -----------------------------------------------------------------------------
# Output
# -----------------------------------------------------------------------------

if [ -t 1 ]; then
    C_STEP=$'\033[1;36m'; C_OK=$'\033[1;32m'; C_WARN=$'\033[1;33m'
    C_ERR=$'\033[1;31m';  C_OFF=$'\033[0m'
else
    C_STEP=''; C_OK=''; C_WARN=''; C_ERR=''; C_OFF=''
fi

step() { printf '\n%s==> %s%s\n' "$C_STEP" "$*" "$C_OFF"; }
info() { printf '    %s\n' "$*"; }
ok()   { printf '    %s%s%s\n' "$C_OK" "$*" "$C_OFF"; }
warn() { printf '    %s! %s%s\n' "$C_WARN" "$*" "$C_OFF"; }
fail() { printf '\n%s!!  %s%s\n\n' "$C_ERR" "$*" "$C_OFF" >&2; exit 1; }

on_exit() {
    local code=$?
    [ "$code" -eq 0 ] && return 0

    printf '\n%s!!  deploy failed%s\n' "$C_ERR" "$C_OFF" >&2

    # Leaving the site in maintenance mode because the deploy died is the worst
    # possible outcome: it stays down until somebody notices and knows the
    # command. This runs whatever else went wrong.
    if [ "$MAINTENANCE_ON" -eq 1 ]; then
        printf '    taking the application out of maintenance mode\n' >&2
        "${APP_DC[@]}" run --rm --no-deps -T php php artisan up >/dev/null 2>&1 \
            || printf '    could NOT -- the site is still down, run: make prod-artisan c=up\n' >&2
    fi

    if [ "$STACK_TOUCHED" -eq 1 ]; then
        printf '    last 40 lines:\n\n' >&2
        "${APP_DC[@]}" logs --tail=40 2>&1 | sed 's/^/    /' >&2 || true
    fi

    if [ "$ROLLBACK_AVAILABLE" -eq 1 ]; then
        printf '\n    the previous images are still tagged :previous -- make rollback\n' >&2
    fi

    printf '\n' >&2
    return $code
}
trap on_exit EXIT

# -----------------------------------------------------------------------------
# Helpers
# -----------------------------------------------------------------------------

env_get() { sed -n "s/^$1=//p" "$ENV_FILE" | tail -n1 | tr -d '\r'; }

require_env() {
    local key value
    for key in "$@"; do
        value=$(env_get "$key")
        [ -n "$value" ] || fail "$key is not set in $ENV_FILE"
    done
}

# Blocks until a compose service reports healthy. Returns 1 on timeout rather
# than hanging: a database that never comes up should stop the deploy, not
# stall it until somebody notices.
wait_healthy() {
    local service=$1 tries=${2:-60} id state
    printf '    waiting for %s' "$service"
    for _ in $(seq 1 "$tries"); do
        id=$("${APP_DC[@]}" ps -q "$service" 2>/dev/null || true)
        if [ -n "$id" ]; then
            state=$(docker inspect -f '{{.State.Health.Status}}' "$id" 2>/dev/null || echo starting)
            if [ "$state" = healthy ]; then
                printf ' healthy\n'
                return 0
            fi
        fi
        printf '.'
        sleep 2
    done
    printf ' timed out\n'
    return 1
}

# -----------------------------------------------------------------------------
# 1. Preflight -- everything that can be checked before anything is changed
# -----------------------------------------------------------------------------

step "Preflight"

command -v docker >/dev/null 2>&1 || fail "docker is not installed"
docker compose version >/dev/null 2>&1 || fail "docker compose v2 is not available"
docker info >/dev/null 2>&1 || fail "cannot talk to the docker daemon (is it running, are you in the docker group?)"

[ -f "$ENV_FILE" ] || fail "$ENV_FILE is missing -- cp .env.production.example $ENV_FILE and fill it in"

require_env \
    API_DOMAIN \
    APP_NAME APP_ENV APP_KEY APP_URL FRONTEND_URL \
    DB_DATABASE DB_USERNAME DB_PASSWORD \
    REDIS_PASSWORD

[ "$(env_get APP_ENV)" = "production" ] \
    || fail "APP_ENV is '$(env_get APP_ENV)', expected 'production'"

# The one check worth failing a deploy over on its own: with APP_DEBUG=true an
# uncaught exception renders a stack trace that lists every environment
# variable, database password included, to whoever triggered it.
[ "$(env_get APP_DEBUG)" = "false" ] \
    || fail "APP_DEBUG must be false in production (it is '$(env_get APP_DEBUG)')"

case "$(env_get APP_KEY)" in
    base64:*) ;;
    *) fail "APP_KEY does not look generated -- run: make prod-key" ;;
esac

# Neither of these is ever typed by a human, so there is no reason for a short
# one -- and both are the only thing in front of a service with no other access
# control.
db_password=$(env_get DB_PASSWORD)
redis_password=$(env_get REDIS_PASSWORD)
[ "${#db_password}" -ge 16 ]    || fail "DB_PASSWORD is shorter than 16 characters"
[ "${#redis_password}" -ge 16 ] || fail "REDIS_PASSWORD is shorter than 16 characters"

# Warnings, not failures: the stack works without these, the product does not.
# Written as `if` rather than `[ ... ] && warn` on purpose -- under `set -e` a
# false test at the end of an && list exits the script, which would make a
# passing check look like a crashed deploy.
if [ "$(env_get MAIL_MAILER)" = "log" ]; then
    warn "MAIL_MAILER=log -- activation and password-reset emails will be written to the log and never sent"
fi

if [ "$(env_get LOG_LEVEL)" = "debug" ]; then
    warn "LOG_LEVEL=debug -- every Log::debug in every package is billed to your disk"
fi

case "$(env_get APP_URL)" in
    https://*) ;;
    *) warn "APP_URL is not https -- generated links will be wrong behind Traefik" ;;
esac

available_kb=$(df -Pk . | awk 'NR==2 {print $4}')
[ "$available_kb" -gt 2097152 ] \
    || warn "less than 2 GB free on this filesystem -- an image build needs room"

ok "environment looks complete"

# -----------------------------------------------------------------------------
# 2. Edge network and proxy
# -----------------------------------------------------------------------------

step "Edge network"

# Deliberately a check and not a `docker network create`, and deliberately not
# `docker compose up` on a proxy.
#
# Traefik and the log stack are shared: they route and observe every service on
# this server, including the frontend. They live in their own repository, are
# deployed once per machine, and an application deploy has no business
# restarting them -- one `git pull` here must not be able to change routing for
# something this repository is not responsible for.
if ! docker network inspect edge >/dev/null 2>&1; then
    fail "the 'edge' network does not exist.

    It belongs to the shared infrastructure stack (Traefik + Loki/Grafana),
    which is deployed once per server, before any application:

        cd /opt/infra && make up

    This repository owns the API and everything only the API uses -- its
    Postgres, its Redis, its nginx. It does not own the proxy."
fi

ok "network 'edge' found"

# -----------------------------------------------------------------------------
# 3. Build
# -----------------------------------------------------------------------------

step "Building images"

app_image=$(env_get APP_IMAGE); app_image=${app_image:-laravel-back-ai-setup/app}
web_image="${app_image}-web"

if docker image inspect "$app_image:current" >/dev/null 2>&1; then
    docker tag "$app_image:current" "$app_image:previous"
    docker tag "$web_image:current" "$web_image:previous"
    ROLLBACK_AVAILABLE=1
    info "current images kept as :previous (make rollback)"
fi

"${APP_DC[@]}" build --pull

revision=$(git rev-parse --short HEAD 2>/dev/null || date -u +%Y%m%d%H%M%S)
docker tag "$app_image:current" "$app_image:$revision"
docker tag "$web_image:current" "$web_image:$revision"

mkdir -p .deploy
printf '%s\n' "$revision" > .deploy/current
ok "built $revision"

# -----------------------------------------------------------------------------
# 4. Data services
# -----------------------------------------------------------------------------

step "Database and cache"

# redis-cache belongs here and not with the rest of the stack, however much it
# looks like a service only the application talks to. `artisan down` two steps
# below writes the maintenance flag through the cache store
# (APP_MAINTENANCE_DRIVER=cache), and that store's connection is REDIS_CACHE_HOST
# -- a different server from the queue's. Starting only `redis` leaves the
# command resolving a hostname that does not exist yet, and the deploy dies at
# the one step whose whole purpose is to keep the site from serving half-migrated
# pages. `run --no-deps` means depends_on cannot save it either.
STACK_TOUCHED=1
"${APP_DC[@]}" up -d postgres redis redis-cache
wait_healthy postgres    || fail "postgres never became healthy"
wait_healthy redis       || fail "redis never became healthy"
wait_healthy redis-cache || fail "redis-cache never became healthy"

# -----------------------------------------------------------------------------
# 5. Backup -- before a migration, never after
# -----------------------------------------------------------------------------

step "Backing up the database"

./docker/scripts/backup.sh || fail "backup failed -- refusing to migrate without one"

# -----------------------------------------------------------------------------
# 6. Migrate and roll forward
# -----------------------------------------------------------------------------

step "Maintenance mode"

# Held in Redis (APP_MAINTENANCE_DRIVER=cache), so one container switching it on
# takes all four out of service. A file-based flag lives inside one container's
# own filesystem and would leave the other three answering normally.
"${APP_DC[@]}" run --rm --no-deps -T php php artisan down --retry=15 >/dev/null
MAINTENANCE_ON=1
ok "503 with Retry-After while migrations run"

step "Migrations"
"${APP_DC[@]}" run --rm --no-deps -T php php artisan migrate --force

step "Starting the application"
"${APP_DC[@]}" up -d --remove-orphans

"${APP_DC[@]}" exec -T php php artisan up >/dev/null
MAINTENANCE_ON=0
ok "maintenance mode off"

# -----------------------------------------------------------------------------
# 7. Prove it works before saying it does
# -----------------------------------------------------------------------------

step "Health check"

healthy=0
printf '    polling /up'
for _ in $(seq 1 45); do
    if "${APP_DC[@]}" exec -T nginx wget -q -O /dev/null http://127.0.0.1/up 2>/dev/null; then
        healthy=1
        printf ' ok\n'
        break
    fi
    printf '.'
    sleep 2
done
[ "$healthy" -eq 1 ] || fail "/up never answered -- the stack is up but not serving"

wait_healthy horizon 30 || warn "horizon is not reporting healthy yet -- check: make prod-logs s=horizon"

# -----------------------------------------------------------------------------
# 8. Tidy up
# -----------------------------------------------------------------------------

step "Cleanup"
docker image prune -f >/dev/null 2>&1 || true
info "dangling images removed"

printf '\n%s==> deployed %s%s\n\n' "$C_OK" "$revision" "$C_OFF"
printf '    api      https://%s\n' "$(env_get API_DOMAIN)"
printf '    logs     make prod-logs   (or Grafana, from the infra stack)\n'
printf '    rollback make rollback\n\n'
