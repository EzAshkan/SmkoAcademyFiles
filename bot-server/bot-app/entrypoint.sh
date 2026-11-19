#!/bin/bash
set -e

echo "▶ Running Laravel optimizations..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "▶ Ensuring storage permissions..."
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "▶ Starting service..."
exec "$@"
