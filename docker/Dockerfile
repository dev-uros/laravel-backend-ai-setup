# syntax=docker/dockerfile:1

# =============================================================================
# base -- the PHP runtime itself, shared by development and production.
#
# One stage on purpose. The PHP version and the extension list are the two
# things dev and prod must never disagree on, and a second Dockerfile is how an
# extension gets added in one place and forgotten in the other -- a difference
# that only shows up after a deploy, in the one environment that matters.
# =============================================================================
FROM php:8.4-fpm-bookworm AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

# pdo_pgsql: Postgres. redis: phpredis (cache + queue + Horizon).
# pcntl + posix: required by Horizon for signal handling and process control.
# gd + exif: Intervention Image, used to re-encode uploaded avatars to WebP.
# exif is what lets it honour a photo's orientation flag instead of rotating it.
RUN install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        redis \
        sockets \
        zip

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]

# =============================================================================
# dev -- what `make up` runs. The code arrives through a bind mount, so nothing
# is copied in: this stage exists to add the tools you reach for at a shell and
# to make the container write files as you rather than as root.
# =============================================================================
FROM base AS dev

# Match the container user to the host user so files written into the bind
# mount (storage/, bootstrap/cache/, vendor/) stay editable on the host.
ARG UID=1000
ARG GID=1000

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        postgresql-client \
    && rm -rf /var/lib/apt/lists/*

RUN groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g "${GID}" www-data

# artisan/composer are executed as www-data (see the Makefile) so that anything
# they write into the bind mount is owned by the host user. Both need a home
# they can actually write to.
ENV COMPOSER_HOME=/var/www/.composer
RUN mkdir -p /var/www/.composer \
    && chown -R www-data:www-data /var/www

COPY docker/php/php.dev.ini /usr/local/etc/php/conf.d/zz-app.ini

# =============================================================================
# vendor -- production dependencies, resolved once and carried into the runtime
# image. Never shipped itself: it holds composer, git and the build cache.
# =============================================================================
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

ENV COMPOSER_ALLOW_SUPERUSER=1

# Dependencies before code, on their own layer. composer.json and composer.lock
# change far less often than app/ does, so an ordinary deploy reuses this layer
# whole and never talks to packagist.
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY . .

# Fires post-autoload-dump, which is what writes bootstrap/cache/packages.php.
# Without it every container rediscovers packages on its first request.
RUN composer dump-autoload --no-dev --optimize --no-interaction

# =============================================================================
# app -- the runtime image. php-fpm by default; horizon and scheduler are the
# same image with a different command, exactly as in development.
#
# The code is baked in. There is no bind mount and no writable copy of the
# source on the server: a deploy replaces the image, it does not edit files.
# =============================================================================
FROM base AS app

COPY docker/php/php.prod.ini  /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.prod.conf /usr/local/etc/php-fpm.d/zzz-app.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint

COPY --from=vendor /var/www/html /var/www/html

# route, view and event caches hold nothing environment specific, so they are
# built here and cost nothing at boot. config:cache is deliberately NOT here --
# see docker/php/entrypoint.sh for why it has to happen at container start.
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        storage/app/private \
        bootstrap/cache \
    && ln -sfn ../storage/app/public public/storage \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["/usr/local/bin/entrypoint"]

CMD ["php-fpm"]

# =============================================================================
# web -- nginx with the application's public/ directory copied in.
#
# This is the answer to "how does nginx see the files without a bind mount".
# It is still the application's own nginx -- it serves public/ and speaks
# FastCGI to php-fpm. TLS, domains and routing between services belong to
# Traefik, one layer further out (compose.edge.yaml).
# =============================================================================
FROM nginx:1.29-alpine AS web

COPY docker/nginx/prod.conf /etc/nginx/conf.d/default.conf

COPY --from=app /var/www/html/public /var/www/html/public

# public/storage is a symlink to ../storage/app/public. The target has to exist
# for nginx to follow it: the avatars volume is mounted here in both this
# container and the php one.
RUN mkdir -p /var/www/html/storage/app/public
