#!/bin/sh
set -e

# --- write .env from container environment (deterministic every start) ---
cat > .env <<EOF
APP_NAME=${APP_NAME:-sistem-sekolah}
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY:-}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}
APP_LOCALE=${APP_LOCALE:-id}
APP_FALLBACK_LOCALE=${APP_FALLBACK_LOCALE:-id}
APP_FAKER_LOCALE=${APP_FAKER_LOCALE:-id_ID}
DB_CONNECTION=${DB_CONNECTION:-pgsql}
DB_HOST=${DB_HOST:-postgres}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE:-sistem_sekolah}
DB_USERNAME=${DB_USERNAME:-sistem_sekolah}
DB_PASSWORD=${DB_PASSWORD:-sistem_sekolah}
SESSION_DRIVER=${SESSION_DRIVER:-database}
SESSION_LIFETIME=${SESSION_LIFETIME:-120}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-redis}
CACHE_STORE=${CACHE_STORE:-redis}
REDIS_HOST=${REDIS_HOST:-redis}
REDIS_PORT=${REDIS_PORT:-6379}
REDIS_PASSWORD=${REDIS_PASSWORD:-null}
LOG_CHANNEL=${LOG_CHANNEL:-stack}
LOG_STACK=${LOG_STACK:-single}
LOG_LEVEL=${LOG_LEVEL:-warning}
FILESYSTEM_DISK=${FILESYSTEM_DISK:-local}
MAIL_MAILER=${MAIL_MAILER:-log}
MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS:-hello@example.com}
MAIL_FROM_NAME=${MAIL_FROM_NAME:-Sistem Sekolah}
VITE_APP_NAME=${VITE_APP_NAME:-Sistem Sekolah}
EOF

# --- permissions (php-fpm runs as www-data) ---
chown -R www-data:www-data storage database bootstrap/cache
chmod -R ug+rw storage database bootstrap/cache

# --- clear stale caches, migrate, seed (idempotent), cache config ---
su www-data -s /bin/sh -c "php artisan config:clear --ansi || true"
su www-data -s /bin/sh -c "php artisan migrate --force --ansi"
su www-data -s /bin/sh -c "php artisan db:seed --force --ansi || true"
su www-data -s /bin/sh -c "php artisan config:cache --ansi"

# --- start daemons ---
php-fpm8.5 -D
nginx -g 'daemon off;'
