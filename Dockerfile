# syntax=docker/dockerfile:1

# ---- 1) front-end assets --------------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
# the build reads the icon data and the docs-less sources only
RUN npm run build

# ---- 2) PHP dependencies ---------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---- 3) runtime: PHP-FPM ---------------------------------------------------------------
FROM php:8.3-fpm-alpine AS app
RUN apk add --no-cache libpng libjpeg-turbo libwebp libzip icu-libs fcgi \
 && apk add --no-cache --virtual .build $PHPIZE_DEPS libpng-dev libjpeg-turbo-dev libwebp-dev libzip-dev icu-dev linux-headers \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" gd pdo_mysql zip intl opcache pcntl \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build \
 && rm -rf /tmp/pear
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-avatarak.ini
COPY docker/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-avatarak.conf

WORKDIR /var/www/html
COPY --from=vendor /app ./
COPY --from=assets /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
 && mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/backups bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production APP_DEBUG=false
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 CMD php artisan about --only=environment >/dev/null 2>&1 || exit 1

# ---- 4) web: nginx with the static assets baked in --------------------------------------
FROM nginx:1.27-alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=assets /app/public /var/www/html/public
