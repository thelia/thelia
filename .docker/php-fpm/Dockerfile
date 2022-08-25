FROM php:8.1-fpm-alpine
WORKDIR "/application"

RUN apk --update --no-cache add git
RUN apk add bash

# Zip extension
RUN apk add --no-cache zip libzip-dev
RUN docker-php-ext-install zip

# Calendar extension
RUN docker-php-ext-install calendar

# Imagick extension
RUN apk add --update --no-cache autoconf g++ imagemagick imagemagick-dev libtool make pcre-dev
RUN pecl install imagick
RUN docker-php-ext-enable imagick

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

# Composer copy and run
COPY --from=composer /usr/bin/composer /usr/bin/composer
CMD composer install ;  php-fpm

# Copy script used at docker-start
COPY docker-init.sh /usr/local/bin/docker-init
