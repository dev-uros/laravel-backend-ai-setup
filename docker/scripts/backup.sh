#!/usr/bin/env bash
#
# Dumps the production database to ./backups, verifies the archive, and deletes
# dumps older than BACKUP_KEEP_DAYS.
#
# deploy.sh runs this before every migration. Run it on a schedule too -- a
# backup that only exists on deploy days is not a backup:
#
#   crontab -e
#   17 3 * * *  cd /opt/api && ./docker/scripts/backup.sh >> /var/log/db-backup.log 2>&1
#
# These dumps sit on the same disk as the database they came from, which covers
# a bad migration and does not cover a dead server. Copy them off the machine.

set -Eeuo pipefail

cd "$(dirname "$0")/../.."

ENV_FILE=.env.production
[ -f "$ENV_FILE" ] || { echo "backup: $ENV_FILE is missing" >&2; exit 1; }

env_get() { sed -n "s/^$1=//p" "$ENV_FILE" | tail -n1 | tr -d '\r'; }

DC=(docker compose -f compose.prod.yaml --env-file "$ENV_FILE")

db_name=$(env_get DB_DATABASE)
db_user=$(env_get DB_USERNAME)
keep_days=$(env_get BACKUP_KEEP_DAYS); keep_days=${keep_days:-14}

[ -n "$db_name" ] && [ -n "$db_user" ] || { echo "backup: DB_DATABASE/DB_USERNAME not set" >&2; exit 1; }

mkdir -p backups

file="backups/${db_name}-$(date -u +%Y%m%dT%H%M%SZ).sql.gz"

# --clean --if-exists so the dump can be restored over an existing database
# without dropping it first. Written straight through gzip: a dump of any size
# never lands on disk uncompressed.
"${DC[@]}" exec -T postgres \
    pg_dump -U "$db_user" -d "$db_name" --clean --if-exists \
    | gzip -9 > "$file"

# A pg_dump that fails mid-stream still leaves a file behind, and gzip happily
# compresses a truncated one. Without this check the first time anybody finds
# out is during a restore.
if ! gzip -t "$file" 2>/dev/null || [ ! -s "$file" ]; then
    rm -f "$file"
    echo "backup: dump failed verification, removed $file" >&2
    exit 1
fi

size=$(du -h "$file" | cut -f1)
echo "    backup: $file ($size)"

deleted=$(find backups -name '*.sql.gz' -type f -mtime "+${keep_days}" -print -delete | wc -l | tr -d ' ')
[ "$deleted" -gt 0 ] && echo "    backup: removed $deleted dump(s) older than ${keep_days} days"

exit 0
