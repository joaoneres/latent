FROM node:22-bookworm AS node

FROM php:8.3-cli-bookworm AS build

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libsqlite3-dev libicu-dev \
    && docker-php-ext-install bcmath intl pcntl pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && cp .env.example .env \
    && php artisan key:generate --force \
    && php artisan package:discover --ansi \
    && mkdir -p database storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && touch database/database.sqlite \
    && php artisan wayfinder:generate --with-form \
    && npm ci \
    && npm run build \
    && rm -rf node_modules .env database/database.sqlite

FROM php:8.3-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx supervisor libzip-dev libsqlite3-dev libicu-dev default-libmysqlclient-dev \
    && docker-php-ext-install bcmath intl pcntl pdo_sqlite pdo_mysql opcache \
    && rm -f /usr/local/etc/php-fpm.d/zz-docker.conf /etc/nginx/sites-enabled/default \
    && mkdir -p /run/php /var/log/supervisor \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=build /app /var/www/html
COPY docker/nginx.conf /etc/nginx/conf.d/latent.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/latent.ini
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
