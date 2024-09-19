FROM php:8.2-fpm-alpine
ARG DEBIAN_FRONTEND=noninteractive

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR "/application"

RUN adduser -D thelia \
&& addgroup thelia thelia \
&& mkdir -p /application \
&& chown -R thelia:thelia /application

RUN apk --update --no-cache add git
RUN apk add bash

# Zip extension
RUN apk add --no-cache zip libzip-dev
RUN docker-php-ext-install zip

# Calendar extension
RUN docker-php-ext-install calendar

# Imagick extension needed for webp
RUN apk add --update --no-cache autoconf g++ imagemagick imagemagick-dev libtool make pcre-dev libgomp
RUN pecl install imagick
RUN docker-php-ext-enable imagick


# GD extension needed for html2pdf
RUN apk add --no-cache freetype libpng libjpeg-turbo freetype-dev libpng-dev libjpeg-turbo-dev && \
  docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
  NPROC=$(grep -c ^processor /proc/cpuinfo 2>/dev/null || 1) && \
  docker-php-ext-install -j$(nproc) gd && \
  apk del --no-cache freetype-dev libpng-dev libjpeg-turbo-dev

# XDebug extension
#RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
#  && pecl install xdebug \
#  && docker-php-ext-enable xdebug \
#  && apk del -f .build-deps

# Intl extension
RUN apk add icu-dev
RUN docker-php-ext-configure intl && docker-php-ext-install intl

# Mysql extension
RUN docker-php-ext-install pdo_mysql

#OPCache
RUN docker-php-ext-install opcache

#APCu
RUN apk --no-cache add pcre-dev ${PHPIZE_DEPS} \
    && pecl install apcu-5.1.22 \
    && docker-php-ext-enable apcu \
    && apk del pcre-dev ${PHPIZE_DEPS}


# Composer copy and run
COPY --from=composer /usr/bin/composer /usr/bin/composer
CMD composer install ;  php-fpm

# Copy script used at docker-start
COPY docker-init.sh /usr/local/bin/docker-init
RUN chmod 755 /usr/local/bin/docker-init

USER thelia
