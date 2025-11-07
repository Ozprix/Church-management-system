#!/usr/bin/env sh
set -euo pipefail

if [ -f /var/www/html/storage/logs/laravel.log ]; then
  truncate -s 0 /var/www/html/storage/logs/laravel.log || true
fi

php artisan config:cache --no-ansi || true
php artisan route:cache --no-ansi || true
php artisan view:cache --no-ansi || true

exec "$@"
