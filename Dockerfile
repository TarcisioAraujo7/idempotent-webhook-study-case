FROM node:22-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm ci
RUN npm run build

FROM php:8.4-cli-bookworm AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .
COPY --from=assets /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint

RUN cp .env.example .env \
    && composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && chmod +x /usr/local/bin/docker-entrypoint \
    && chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
