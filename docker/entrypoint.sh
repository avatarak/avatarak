#!/bin/sh
set -e
cd /var/www/html
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private/backups
chown -R www-data:www-data storage bootstrap/cache

# Only the main php-fpm container prepares the application; the queue and scheduler containers
# (same image, different command) must not race it.
if [ "$1" = "php-fpm" ]; then
    if [ -z "$APP_KEY" ]; then
        echo "APP_KEY is empty. Generate one with: docker compose run --rm app php artisan key:generate --show" >&2
        exit 1
    fi
    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        php artisan migrate --force --no-interaction
    fi
    php artisan package:discover --no-interaction
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

exec "$@"
