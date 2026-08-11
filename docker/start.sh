#!/usr/bin/env sh
set -eu

: "${PORT:=10000}"

# Render injecte le port seulement au démarrage.
sed "s/__RENDER_PORT__/${PORT}/g" /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

php artisan storage:link --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force --no-interaction
php artisan cts:provision-admin --no-interaction

php-fpm -D
exec nginx -g 'daemon off;'
