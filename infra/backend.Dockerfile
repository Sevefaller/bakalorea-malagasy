FROM php:8.5-fpm-alpine
RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev libpq-dev sqlite-dev \
    && docker-php-ext-install -j4 pdo_pgsql pdo_sqlite mbstring intl zip pcntl
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /srv/backend
COPY backend/ ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
CMD ["php-fpm"]
