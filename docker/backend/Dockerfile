# syntax=docker/dockerfile:1.6
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
        bash \
        git \
        curl \
        unzip \
        icu-dev \
        libpq-dev \
        libzip-dev \
        oniguruma-dev \
        libxml2-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        autoconf \
        g++ \
        make \
        postgresql-client \
        nginx \
        supervisor \
        tzdata \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
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
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf g++ make

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
