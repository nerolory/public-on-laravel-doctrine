FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev libonig-dev libxml2-dev unzip \
    && docker-php-ext-install pdo_pgsql zip pcntl bcmath mbstring dom xml xmlwriter \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/proxies \
    && chown -R www-data:www-data storage bootstrap/cache
CMD ["php-fpm"]
