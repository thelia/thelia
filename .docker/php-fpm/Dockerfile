# ------ PHP 7 DOCKER (uncomment to use it)  ------- #

#FROM phpdockerio/php74-fpm:latest
#WORKDIR "/application"
#
## Fix debconf warnings upon build
#ARG DEBIAN_FRONTEND=noninteractive
#
## Install selected extensions and other stuff
#RUN apt-get update \
#    && apt-get -y --no-install-recommends install php-mysql php7.4-mysql php7.4-gd php-imagick php7.4-intl php-yaml php-xdebug \
#    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*
#
## Install git
#RUN apt-get update \
#    && apt-get -y install git \
#    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*
#
#RUN apt-get update \
#    &&  apt-get -y install vim \
#    && apt-get clean;
#
## Copy script used at docker-start
#COPY docker-init.sh /usr/local/bin/docker-init


# ------ PHP 8 DOCKER (uncomment to use it) ------- #

FROM php:8.0.2-fpm-alpine
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
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
  && pecl install xdebug-3.0.0 \
  && docker-php-ext-enable xdebug \
  && apk del -f .build-deps

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
