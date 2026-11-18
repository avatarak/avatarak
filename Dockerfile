ARG IMAGE_REGISTRY=docker.io/library

# ---- 1) front-end assets --------------------------------------------------------------
FROM ${IMAGE_REGISTRY}/node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY scripts ./scripts
COPY resources ./resources
COPY public ./public
# the build reads the icon data and the docs-less sources only
RUN npm run build

# ---- 2) PHP dependencies ---------------------------------------------------------------
FROM ${IMAGE_REGISTRY}/composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---- 3) runtime: PHP-FPM ---------------------------------------------------------------
FROM ${IMAGE_REGISTRY}/php:8.5-fpm-alpine AS app
RUN apk add --no-cache libpng libjpeg-turbo libwebp libzip icu-libs fcgi mariadb-client \
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
COPY --from=assets /app/resources/data ./resources/data
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
 && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private/backups bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production APP_DEBUG=false
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
HEALTHCHECK --interval=30s --timeout=5s --start-period=120s --retries=3 CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong

# ---- 4) web: nginx with the static assets baked in --------------------------------------
FROM ${IMAGE_REGISTRY}/nginx:stable-alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=assets /app/public /var/www/html/public
