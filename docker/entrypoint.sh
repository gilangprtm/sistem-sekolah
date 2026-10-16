#!/bin/sh
set -eu

# Coolify injects runtime environment variables; do not overwrite .env.
: "${APP_KEY:?APP_KEY must be set in Coolify}"
if [ -z "${DB_URL:-}" ]; then
    : "${DB_HOST:?Set DB_URL or DB_HOST in Coolify}"
    : "${DB_DATABASE:?Set DB_URL or DB_DATABASE in Coolify}"
    : "${DB_USERNAME:?Set DB_URL or DB_USERNAME in Coolify}"
fi

role="${APP_ROLE:-web}"
case "$role" in
    web|worker) ;;
    *) echo "Unsupported APP_ROLE: $role (expected web or worker)" >&2; exit 1 ;;
esac

# Public uploads can be mounted as a persistent volume in Coolify.
mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

su www-data -s /bin/sh -c "php artisan config:clear --ansi"

if [ "$role" = "worker" ]; then
    # Deploy a second Coolify application using the same Dockerfile with APP_ROLE=worker.
    # The web container owns migrations; worker retries while DB/Redis become available.
    : "${REDIS_URL:?REDIS_URL must be set for the queue worker}"
    : "${QUEUE_CONNECTION:=redis}"
    if [ "$QUEUE_CONNECTION" != "redis" ]; then
        echo "APP_ROLE=worker requires QUEUE_CONNECTION=redis" >&2
        exit 1
    fi
    su www-data -s /bin/sh -c "php artisan config:cache --ansi"
    exec su www-data -s /bin/sh -c "exec php artisan queue:work redis --queue=default --sleep=3 --tries=3 --timeout=60 --max-time=3600"
fi

su www-data -s /bin/sh -c "php artisan migrate --force --ansi"
su www-data -s /bin/sh -c "php artisan storage:link --ansi"
su www-data -s /bin/sh -c "php artisan config:cache --ansi"

php-fpm8.5 -D
exec nginx -g 'daemon off;'
