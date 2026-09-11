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

    # -------------------------------------------------------------------------
    # Sizing for the machine this container happens to be on.
    #
    # php-fpm and opcache are the two things here that cannot read the
    # environment themselves: FPM does not interpolate variables in a pool file
    # (it fails to parse one outright), and php.ini has no such syntax either.
    # Everything else -- memory limits, Postgres, Redis, Horizon -- is a
    # ${VAR:-default} in compose.prod.yaml or an env() in config/.
    #
    # Both files are written ONLY when the variable is set, so an unset
    # environment leaves docker/php/www.prod.conf and php.prod.ini exactly as
    # they were built. A large server configures nothing and gets the defaults;
    # a small one overrides them in .env.production.
    #
    # conf.d and php-fpm.d are both read in alphabetical order, and `zzz-` comes
    # after the `zz-app` and `www` files baked into the image -- so these win.
    # -------------------------------------------------------------------------
    if [ -n "${PHP_FPM_MAX_CHILDREN:-}" ]; then
        # The spare-server values are derived rather than configured. php-fpm
        # refuses to start when min/max spare exceed max_children, and a lowered
        # worker count with the image's original 2/6 spares does exactly that --
        # a container that will not boot and says only "failed to post process
        # the configuration".
        _min_spare=$(( PHP_FPM_MAX_CHILDREN / 4 ))
        [ "$_min_spare" -lt 1 ] && _min_spare=1
        _max_spare=$(( PHP_FPM_MAX_CHILDREN / 2 ))
        [ "$_max_spare" -lt "$_min_spare" ] && _max_spare=$_min_spare

        cat > /usr/local/etc/php-fpm.d/zzz-tune.conf <<FPM
[www]
pm.max_children = ${PHP_FPM_MAX_CHILDREN}
pm.start_servers = ${_max_spare}
pm.min_spare_servers = ${_min_spare}
pm.max_spare_servers = ${_max_spare}
FPM
    fi

    if [ -n "${PHP_OPCACHE_MEMORY:-}" ]; then
        printf 'opcache.memory_consumption = %s\n' "$PHP_OPCACHE_MEMORY" \
            > /usr/local/etc/php/conf.d/zzz-tune.ini
    fi

    # Config is cached here rather than in the image because the values come
    # from the container's environment, which only exists on the server. Caching
    # it at build time would bake production credentials into an image that gets
    # pushed around, and would make every later edit to .env.production do
    # nothing at all. view/event caches are the opposite -- nothing in them
    # depends on the environment -- so those are built into the image.
    #
    # As www-data, so the file it writes is owned by the user that later reads
    # it. Each container has its own bootstrap/cache (an image layer, not a
    # shared volume), so php, horizon and scheduler cannot race one another.
    setpriv --reuid=www-data --regid=www-data --clear-groups php artisan config:cache

    # Routes are cached here for the same reason, and strictly AFTER the config:
    # Livewire v4 builds its endpoint out of the application key --
    # `substr(hash('sha256', config('app.key').'livewire-endpoint'), 0, 8)` -- so
    # a route cache built at image time carries the hash of an empty key while the
    # rendered page carries the hash of the real one. Every Livewire POST then 404s
    # against a route that does not exist, which is how /pulse and /horizon end up
    # as a page of skeletons with nothing in the log.
    setpriv --reuid=www-data --regid=www-data --clear-groups php artisan route:cache

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
php artisan route:cache

exec "$@"
