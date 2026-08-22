#!/usr/bin/env bash
#
# Puts the previous images back and restarts the stack.
#
#   make rollback
#
# WHAT THIS DOES NOT DO: revert migrations. Code goes back, the database does
# not. If the deploy you are undoing added a column, the old code simply ignores
# it and everything is fine; if it dropped or renamed one, the old code will
# break and you need the dump deploy.sh took just before migrating:
#
#   gunzip -c backups/<name>-<timestamp>.sql.gz \
#     | docker compose -f compose.prod.yaml --env-file .env.production \
#         exec -T postgres psql -U <user> -d <database>
#
# That is why `down` migrations are worth writing for anything destructive.

set -Eeuo pipefail

cd "$(dirname "$0")/../.."

ENV_FILE=.env.production
[ -f "$ENV_FILE" ] || { echo "rollback: $ENV_FILE is missing" >&2; exit 1; }

env_get() { sed -n "s/^$1=//p" "$ENV_FILE" | tail -n1 | tr -d '\r'; }

DC=(docker compose -f compose.prod.yaml --env-file "$ENV_FILE")

app_image=$(env_get APP_IMAGE); app_image=${app_image:-laravel-back-ai-setup/app}
web_image="${app_image}-web"

docker image inspect "$app_image:previous" >/dev/null 2>&1 \
    || { echo "rollback: no $app_image:previous image -- nothing to roll back to" >&2; exit 1; }

echo "==> rolling back to the previous images"

# The current pair becomes :failed rather than being thrown away: whatever went
# wrong is usually easier to look at than to rebuild.
if docker image inspect "$app_image:current" >/dev/null 2>&1; then
    docker tag "$app_image:current" "$app_image:failed"
    docker tag "$web_image:current" "$web_image:failed"
    echo "    the images being replaced are tagged :failed"
fi

docker tag "$app_image:previous" "$app_image:current"
docker tag "$web_image:previous" "$web_image:current"

"${DC[@]}" up -d --force-recreate php horizon scheduler nginx

# Whatever state the failed deploy left behind.
"${DC[@]}" exec -T php php artisan up >/dev/null 2>&1 || true

printf '    waiting for /up'
for _ in $(seq 1 45); do
    if "${DC[@]}" exec -T nginx wget -q -O /dev/null http://127.0.0.1/up 2>/dev/null; then
        printf ' ok\n'
        echo "==> rolled back"
        exit 0
    fi
    printf '.'
    sleep 2
done

printf ' timed out\n'
echo "rollback: the previous images are running but /up is not answering" >&2
exit 1
