FROM php:8.1-fpm-alpine
WORKDIR "/application"

RUN apk --update --no-cache add git
RUN apk add bash

# Zip extension
RUN apk add --no-cache zip libzip-dev
RUN docker-php-ext-install zip

# Calendar extension
RUN docker-php-ext-install calendar

# GD extension
RUN apk add --no-cache freetype libpng libjpeg-turbo freetype-dev libpng-dev libjpeg-turbo-dev && \
  docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
  NPROC=$(grep -c ^processor /proc/cpuinfo 2>/dev/null || 1) && \
  docker-php-ext-install -j$(nproc) gd && \
  apk del --no-cache freetype-dev libpng-dev libjpeg-turbo-dev

# XDebug extension
#RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
#  && pecl install xdebug-3.0.0 \
#  && docker-php-ext-enable xdebug \
#  && apk del -f .build-deps

# Intl extension
RUN apk add icu-dev
RUN docker-php-ext-configure intl && docker-php-ext-install intl

# Mysql extension
RUN docker-php-ext-install pdo_mysql

# Composer copy and run
COPY --from=composer /usr/bin/composer /usr/bin/composer
CMD composer install ;  php-fpm

# Copy script used at docker-start
COPY docker-init.sh /usr/local/bin/docker-init
