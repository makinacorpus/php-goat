FROM php:8.0-rc
RUN apt-get update \
    && apt-get install -y libpq-dev \
    && docker-php-ext-install -j$(nproc) pgsql \
    && pecl install xdebug-3.0.0 \
    && docker-php-ext-enable pgsql \
    && docker-php-ext-enable xdebug
RUN mkdir -p /usr/local/etc/php/conf.d/
COPY ./php74/etc/php.ini /usr/local/etc/php/php.ini
COPY ./php74/etc/conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
# COPY ./php74/etc/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
