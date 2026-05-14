#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Ensure writable dirs exist (volume mounts may shadow built ones)
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

# Ensure nginx runtime dirs are writable by the www-data user that
# nginx runs as. Without this, request bodies larger than the in-memory
# buffer (e.g. base64 photo uploads on clock-in) fail with:
#   open() ".../client_body/0000000003" failed (13: Permission denied)
mkdir -p \
    /var/lib/nginx/tmp/client_body \
    /var/lib/nginx/tmp/proxy \
    /var/lib/nginx/tmp/fastcgi \
    /var/lib/nginx/tmp/uwsgi \
    /var/lib/nginx/tmp/scgi \
    /var/log/nginx \
    /run/nginx
chown -R www-data:www-data /var/lib/nginx /var/log/nginx /run/nginx || true
chmod -R u+rwX,g+rX /var/lib/nginx || true

# Generate APP_KEY only if missing
if [ -z "${APP_KEY:-}" ] || [ "${APP_KEY:-}" = "base64:" ]; then
    echo "[entrypoint] APP_KEY not set — generating one (ephemeral). Set APP_KEY in .env to make it stable."
    export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
fi

# Wait for Postgres
if [ -n "${DB_HOST:-}" ] && [ "${DB_CONNECTION:-pgsql}" = "pgsql" ]; then
    echo "[entrypoint] Waiting for Postgres at ${DB_HOST}:${DB_PORT:-5432}..."
    for i in $(seq 1 60); do
        if pg_isready -h "${DB_HOST}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-postgres}" >/dev/null 2>&1; then
            echo "[entrypoint] Postgres is ready."
            break
        fi
        sleep 1
    done
fi

# Storage symlink (idempotent)
php artisan storage:link --force >/dev/null 2>&1 || true

# Run migrations on boot if requested
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[entrypoint] Running database migrations..."
    php artisan migrate --force --no-interaction || echo "[entrypoint] WARN: migration failed (continuing)"
fi

# Cache configs in production
if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# Translate booleans for supervisor's [program:queue-worker] autostart
export RUN_QUEUE_WORKER="${RUN_QUEUE_WORKER:-true}"

exec "$@"
