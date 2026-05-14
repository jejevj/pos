# syntax=docker/dockerfile:1.6
FROM php:8.3-fpm-alpine AS base

# phpredis pinned to a known-good release compatible with PHP 8.3.
# We build from the GitHub source (shallow clone of an immutable tag)
# instead of `pecl install redis`, which has been intermittently
# unavailable from pecl.php.net.
ARG PHPREDIS_VERSION=6.0.2

# Runtime + build-time packages. The build-only ones are tagged with the
# `.build-deps` virtual package so we can `apk del` them at the end and
# keep the final image lean.
RUN set -eux; \
    apk add --no-cache \
        bash \
        curl \
        git \
        unzip \
        tzdata \
        icu-libs \
        libpq \
        libzip \
        oniguruma \
        libxml2 \
        libpng \
        libjpeg-turbo \
        freetype \
        postgresql-client \
        nginx \
        supervisor; \
    apk add --no-cache --virtual .build-deps \
        autoconf \
        g++ \
        make \
        pkgconf \
        re2c \
        icu-dev \
        libpq-dev \
        libzip-dev \
        oniguruma-dev \
        libxml2-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        zip \
        mbstring \
        exif \
        pcntl \
        gd \
        opcache; \
    git clone --depth 1 --branch "${PHPREDIS_VERSION}" \
        https://github.com/phpredis/phpredis.git /tmp/phpredis; \
    cd /tmp/phpredis; \
    phpize; \
    ./configure --enable-redis; \
    make -j"$(nproc)"; \
    make install; \
    cd /; \
    docker-php-ext-enable redis; \
    php -m | grep -qi '^redis$'; \
    rm -rf /tmp/phpredis; \
    apk del --no-network .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Cache composer install
COPY backend-api/composer.json backend-api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Copy the rest of the app
COPY backend-api/ ./

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# PHP / nginx / supervisor configs
COPY docker/backend/php.ini /usr/local/etc/php/conf.d/zz-custom.ini
COPY docker/backend/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/backend/nginx.conf /etc/nginx/nginx.conf
COPY docker/backend/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/backend/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
