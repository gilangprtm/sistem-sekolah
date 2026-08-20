# syntax=docker/dockerfile:1

# 1) Composer deps — FULL deps (NOT --no-dev; proven pattern for Laravel 13 + Inertia)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist

# 2) Frontend build — needs PHP + full vendor (wayfinder plugin shells out to artisan)
FROM ubuntu:24.04 AS frontend
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg lsb-release \
    && curl -fsSL https://packages.sury.org/php/apt.gpg -o /etc/apt/trusted.gpg.d/php.gpg \
    && echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get update && apt-get install -y --no-install-recommends \
        php8.5-cli php8.5-mbstring php8.5-xml php8.5-curl php8.5-sqlite3 php8.5-zip php8.5-intl php8.5-bcmath php8.5-pgsql php8.5-redis \
        nodejs \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY composer.json composer.lock ./
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN php artisan package:discover --ansi
RUN npm run build

# 3) Runtime — php8.5-fpm + nginx on Ubuntu 24.04
FROM ubuntu:24.04 AS runtime
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg lsb-release \
    && curl -fsSL https://packages.sury.org/php/apt.gpg -o /etc/apt/trusted.gpg.d/php.gpg \
    && echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list \
    && apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        curl \
        php8.5-cli php8.5-fpm php8.5-mbstring php8.5-xml php8.5-curl php8.5-sqlite3 php8.5-zip php8.5-intl php8.5-bcmath php8.5-pgsql php8.5-redis \
        composer sqlite3 unzip \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY . .
RUN rm -f public/hot \
    && composer install --no-scripts --no-interaction --prefer-dist \
    && php artisan package:discover --ansi \
    && rm -rf bootstrap/cache/*.php
COPY docker/nginx.conf /etc/nginx/sites-enabled/default
COPY docker/php.ini /etc/php/8.5/fpm/conf.d/99-app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
