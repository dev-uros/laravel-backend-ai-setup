# syntax=docker/dockerfile:1

FROM php:8.4-fpm-bookworm

# Match the container user to the host user so files written into the bind
# mount (storage/, bootstrap/cache/, vendor/) stay editable on the host.
ARG UID=1000
ARG GID=1000

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# pdo_pgsql: Postgres. redis: phpredis (cache + queue + Horizon).
# pcntl + posix: required by Horizon for signal handling and process control.
RUN install-php-extensions \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        redis \
        sockets \
        zip

RUN groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g "${GID}" www-data

# artisan/composer are executed as www-data (see the Makefile) so that anything
# they write into the bind mount is owned by the host user. Both need a home
# they can actually write to.
ENV COMPOSER_HOME=/var/www/.composer
RUN mkdir -p /var/www/.composer \
    && chown -R www-data:www-data /var/www

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
