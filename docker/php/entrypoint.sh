#!/bin/sh
set -e

# -----------------------------------------------------------------------------
# Runs for php-fpm, horizon, scheduler and every one-off `artisan` container:
# they are all the same image with a different command.
# -----------------------------------------------------------------------------

if [ "$(id -u)" = "0" ]; then
    # storage/app/public is a named volume. Docker seeds it from the image the
    # first time it is mounted, but a volume restored from a backup, or one
    # created before this image existed, can arrive owned by root -- and then
    # avatar uploads fail with a permission error that nothing in the logs
    # explains. Only root can fix it, and it has to happen before the
    # application starts.
    #
    # Not recursive: new files are created by www-data anyway, and a chown -R
    # over a volume holding thousands of avatars would be paid on every start.
    chown www-data:www-data /var/www/html/storage/app/public || true

    # Config is cached here rather than in the image because the values come
    # from the container's environment, which only exists on the server. Caching
    # it at build time would bake production credentials into an image that gets
    # pushed around, and would make every later edit to .env.production do
    # nothing at all. route/view/event caches are the opposite -- nothing in
    # them depends on the environment -- so those are built into the image.
    #
    # As www-data, so the file it writes is owned by the user that later reads
    # it. Each container has its own bootstrap/cache (an image layer, not a
    # shared volume), so php, horizon and scheduler cannot race one another.
    setpriv --reuid=www-data --regid=www-data --clear-groups php artisan config:cache

    # php-fpm is the one command whose master process must stay root. The
    # official image points error_log and slowlog at /proc/self/fd/2, and only
    # root can reopen the container's stderr -- drop privileges first and fpm
    # exits 78 on every start with "failed to open error_log". Its workers drop
    # to www-data through `user =` in docker/php/www.prod.conf, which is how
    # php-fpm is designed to work.
    #
    # Everything else -- horizon, schedule:work, migrations, a shell -- has no
    # such master and runs unprivileged from here.
    case "$1" in
        php-fpm) exec "$@" ;;
        *)       exec setpriv --reuid=www-data --regid=www-data --clear-groups "$@" ;;
    esac
fi

# Already unprivileged: the container was started with an explicit `user:`.
php artisan config:cache

exec "$@"
