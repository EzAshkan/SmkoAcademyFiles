FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
    bash \
    curl \
    zip \
    unzip \
    git \
    icu-dev \
    linux-headers \
    $PHPIZE_DEPS

RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    bcmath \
    pcntl \
    intl \
    opcache

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative

RUN mkdir -p storage/framework/{sessions,views,cache} \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY php.ini /usr/local/etc/php/conf.d/app.ini

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
