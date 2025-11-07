# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.3

FROM composer:2.7 AS composer_deps
WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

COPY . .

RUN composer dump-autoload --optimize

FROM php:${PHP_VERSION}-fpm-alpine AS runtime

ARG USER_ID=1000
ARG GROUP_ID=1000

ENV TZ=UTC \
    APP_ENV=production \
    PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_ENABLE_CLI=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_OPCACHE_MAX_ACCELERATED_FILES=20000 \
    PHP_OPCACHE_MEMORY_CONSUMPTION=192

RUN apk add --no-cache \
        bash \
        curl \
        git \
        libzip-dev \
        tzdata \
        shadow \
    && docker-php-ext-install pdo_mysql bcmath zip opcache \
    && addgroup -g "${GROUP_ID}" app \
    && adduser -G app -D -u "${USER_ID}" app \
    && mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R app:app /var/www/html

COPY --from=composer_deps --chown=app:app /var/www/html /var/www/html

COPY docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint

RUN chmod +x /usr/local/bin/app-entrypoint

WORKDIR /var/www/html

USER app

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=3s --start-period=20s --retries=3 \
    CMD php artisan octane:status >/dev/null 2>&1 || php -v >/dev/null 2>&1

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
