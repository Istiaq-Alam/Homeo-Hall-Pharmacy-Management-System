#!/bin/sh
set -eu

PORT="${PORT:-10000}"

# Render assigns the port at runtime. Apache's default configuration listens on
# 80, so rewrite both the listener and the virtual host before starting.
sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p \
    bootstrap/cache \
    storage/app/pdf \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

chown -R www-data:www-data storage bootstrap/cache

if [ -z "${APP_KEY:-}" ]; then
    echo "FATAL: APP_KEY is not set. Add the existing Laravel application key to the service environment." >&2
    exit 1
fi

# Configuration is cached at boot so environment values are read at runtime,
# never baked into the image. Database is not touched here and migrations are
# never run automatically.
php artisan config:cache >/dev/null 2>&1 \
    || echo "WARNING: could not cache configuration at startup; continuing with runtime config." >&2

exec apache2-foreground
