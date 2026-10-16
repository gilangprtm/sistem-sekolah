#!/bin/sh
set -eu

# Coolify injects environment variables directly; do not rewrite .env on restart.
: "${APP_KEY:?APP_KEY must be set in Coolify}"
: "${DB_HOST:?DB_HOST must be set in Coolify}"
: "${DB_DATABASE:?DB_DATABASE must be set in Coolify}"
: "${DB_USERNAME:?DB_USERNAME must be set in Coolify}"

# The public uploads directory may be a Coolify bind mount.
mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

su www-data -s /bin/sh -c "php artisan config:clear --ansi"
su www-data -s /bin/sh -c "php artisan migrate --force --ansi"
su www-data -s /bin/sh -c "php artisan storage:link --ansi"
su www-data -s /bin/sh -c "php artisan config:cache --ansi"

php-fpm8.5 -D
exec nginx -g 'daemon off;'
