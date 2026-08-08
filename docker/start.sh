#!/usr/bin/env bash
set -euo pipefail

: "${PORT:=10000}"

# Render provides PORT at runtime. Apache defaults to 80.
sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan storage:link --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

exec apache2-foreground
