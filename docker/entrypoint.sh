#!/bin/sh
set -e

# --- write .env from container environment (deterministic every start) ---
cat > .env <<EOF
APP_NAME=${APP_NAME:-laravel-shadcn-admin-dashboard}
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY:-}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}
APP_LOCALE=${APP_LOCALE:-en}
APP_FALLBACK_LOCALE=${APP_FALLBACK_LOCALE:-en}
APP_FAKER_LOCALE=${APP_FAKER_LOCALE:-en_US}
DB_CONNECTION=${DB_CONNECTION:-sqlite}
SESSION_DRIVER=${SESSION_DRIVER:-database}
SESSION_LIFETIME=${SESSION_LIFETIME:-120}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-database}
CACHE_STORE=${CACHE_STORE:-database}
LOG_CHANNEL=${LOG_CHANNEL:-stack}
LOG_STACK=${LOG_STACK:-single}
LOG_LEVEL=${LOG_LEVEL:-warning}
FILESYSTEM_DISK=${FILESYSTEM_DISK:-local}
MAIL_MAILER=${MAIL_MAILER:-log}
MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS:-hello@example.com}
MAIL_FROM_NAME=${MAIL_FROM_NAME:-Laravel}
VITE_APP_NAME=${VITE_APP_NAME:-Laravel}
EOF

# --- permissions (php-fpm runs as www-data) ---
chown -R www-data:www-data storage database bootstrap/cache
chmod -R ug+rw storage database bootstrap/cache

# --- DB bootstrap ---
if [ ! -f database/database.sqlite ]; then
    su www-data -s /bin/sh -c "touch database/database.sqlite"
fi

# --- clear stale caches, migrate, cache config ---
su www-data -s /bin/sh -c "php artisan config:clear --ansi || true"
su www-data -s /bin/sh -c "php artisan migrate --force --ansi"
su www-data -s /bin/sh -c "php artisan config:cache --ansi"

# --- start daemons ---
php-fpm8.5 -D
nginx -g 'daemon off;'
