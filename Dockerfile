FROM node:22-bookworm-slim@sha256:a6385bc33f0f9d6a08482c87f52baee537a9184e2b47dea1ed97caac62f8960c AS frontend
WORKDIR /build
COPY vue-frontend/package.json vue-frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vue-frontend/ ./
RUN npm run build

FROM php:8.3-fpm-bookworm@sha256:bf50afc08abc872c14e4345db9e798aff0d000c5038c518256df2755beae002c AS php-base
ARG PHP_BUILD_JOBS=2
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libfcgi-bin libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"${PHP_BUILD_JOBS}" bcmath gd intl pdo_mysql zip
COPY --from=composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac /usr/bin/composer /usr/local/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-zaimedia.ini
COPY docker/fpm.conf /usr/local/etc/php-fpm.d/zz-zaimedia.conf
WORKDIR /var/www/html

FROM php-base AS backend-build
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY laravel-api/composer.json laravel-api/composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache composer install --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader
COPY laravel-api/ ./
RUN mkdir -p storage/app/private/books/pdf storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs public/uploads bootstrap/cache \
    && composer dump-autoload --optimize --no-scripts \
    && php artisan package:discover --ansi

FROM php-base AS app
COPY --from=backend-build --chown=www-data:www-data /var/www/html /var/www/html
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/zaimedia-entrypoint
COPY --chmod=755 docker/healthcheck.sh /usr/local/bin/zaimedia-healthcheck
USER www-data
ENTRYPOINT ["zaimedia-entrypoint"]
CMD ["php-fpm"]

FROM nginx:stable-alpine@sha256:970364228b1e4f932abc22d321932816782cca8a20b85a77552a7cfe9acfb3b5 AS web
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY --from=frontend /build/dist /srv/zaimedia
RUN mkdir -p /var/www/html/public/uploads \
    && chown 33:33 /var/www/html/public/uploads
USER nginx
EXPOSE 8080
ENTRYPOINT ["nginx"]
CMD ["-g", "daemon off;"]
