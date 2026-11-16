# syntax=docker/dockerfile:1.7

# ---------------------------------------------------------------------------
# 1. Frontend assets (Vite, Tailwind CSS 4)
# ---------------------------------------------------------------------------
FROM node:22-bookworm-slim AS assets

WORKDIR /app

COPY hpms/package.json hpms/package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY hpms/ ./
RUN npm run build

# ---------------------------------------------------------------------------
# 2. Composer dependencies (production only, committed lockfile)
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY hpms/composer.json hpms/composer.lock ./
# --no-scripts keeps the build free of database access; the package manifest
# is rebuilt lazily by Laravel at runtime.
RUN composer install --no-dev --no-scripts --no-autoloader \
        --prefer-dist --no-interaction --no-progress

COPY hpms/ ./
RUN composer dump-autoload --no-dev --optimize --no-scripts

# ---------------------------------------------------------------------------
# 3. Runtime: Apache + mod_php, production extensions, WeasyPrint/Chromium for PDFs
# ---------------------------------------------------------------------------
FROM php:8.4-apache AS runtime

WORKDIR /var/www/html

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        chromium \
        fonts-dejavu-core \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        weasyprint \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" bcmath gd mbstring opcache pdo_mysql zip \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache-hardening.conf /etc/apache2/conf-available/zz-hardening.conf
# Debian ships `Alias /icons/ "/usr/share/apache2/icons/"`. That alias wins over
# the application's own public/icons directory, so every logo, favicon and PWA
# icon 404s in production. Drop it; docker/apache.conf aliases /icons/ to the app.
RUN a2enconf zz-hardening \
    && rm -f /etc/apache2/mods-enabled/alias.conf
COPY docker/php-production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Application code, production dependencies and compiled assets.
COPY --from=vendor /app/vendor ./vendor
COPY hpms/ .
COPY docs/USER_MANUAL_BN.pdf ./docs/USER_MANUAL_BN.pdf
COPY --from=assets /app/public/build ./public/build

# Only writable runtime locations; everything else stays root-owned/read-only.
RUN mkdir -p \
        bootstrap/cache \
        storage/app/pdf \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/entrypoint"]
