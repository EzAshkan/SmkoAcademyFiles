# SMKO Files — one image for the web app, the queue worker and the scheduler.
# vendor/ and public/build/ are built before the image (composer install / npm run build),
# so the server never needs Packagist or npm.
FROM php:8.3-fpm-alpine

# The official Alpine mirror isn't reachable from Iranian servers; use a local mirror
# (apk still verifies every package's signature). Override with --build-arg ALPINE_MIRROR=...
ARG ALPINE_MIRROR=https://mirror.arvancloud.ir/alpine
RUN sed -i "s#https\?://dl-cdn.alpinelinux.org/alpine#${ALPINE_MIRROR}#" /etc/apk/repositories \
 && apk add --no-cache nginx su-exec mariadb-client icu-libs libzip libpng libjpeg-turbo freetype \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev linux-headers \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip gd exif bcmath opcache pcntl \
 && apk del .build-deps \
 && rm -rf /tmp/* /var/cache/apk/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-smko.ini
COPY docker/fpm.conf /usr/local/etc/php-fpm.d/zz-smko.conf
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/entrypoint.sh /usr/local/bin/smko-entrypoint
# (strip Windows line endings in case the file was edited on Windows)
RUN tr -d '\r' < /usr/local/bin/smko-entrypoint > /tmp/ep && mv /tmp/ep /usr/local/bin/smko-entrypoint \
 && chmod +x /usr/local/bin/smko-entrypoint

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
RUN rm -f .env database/*.sqlite* \
 && mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
ENTRYPOINT ["smko-entrypoint"]
CMD ["web"]
