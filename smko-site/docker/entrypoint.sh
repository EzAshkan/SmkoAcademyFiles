#!/bin/sh
# web       — nginx + php-fpm (the website)
# worker    — background jobs (announcements)
# scheduler — new-file alerts, health checks, cleanup (every minute)
# anything else is run as a command (e.g. "php artisan migrate")
set -e
cd /var/www/html

# Storage is a volume: make sure its folders exist and belong to www-data.
mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

cache_config() {
    su-exec www-data php artisan config:cache >/dev/null 2>&1 || php artisan config:cache
    su-exec www-data php artisan route:cache >/dev/null 2>&1 || php artisan route:cache
    su-exec www-data php artisan view:cache >/dev/null 2>&1 || php artisan view:cache
}

case "$1" in
    web)
        cache_config
        php artisan migrate --force --no-interaction
        php-fpm -D
        exec nginx -g 'daemon off;'
        ;;
    worker)
        cache_config
        exec su-exec www-data php artisan queue:work --tries=3 --max-time=3600 --sleep=3
        ;;
    scheduler)
        cache_config
        exec su-exec www-data php artisan schedule:work
        ;;
    *)
        exec "$@"
        ;;
esac
