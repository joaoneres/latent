#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    database \
    /run/php

touch database/database.sqlite

php artisan migrate --force
php artisan storage:link || true
php artisan config:cache
php artisan view:cache
php docker/ensure-admin.php

chown -R www-data:www-data storage bootstrap/cache database public/storage

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
