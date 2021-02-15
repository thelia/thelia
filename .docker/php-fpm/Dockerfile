FROM php:8.0.2-fpm-alpine
WORKDIR "/application"

RUN apk --update --no-cache add git
RUN apk add bash
RUN apk add --no-cache zip libzip-dev
RUN docker-php-ext-install zip
RUN apk add --no-cache freetype libpng libjpeg-turbo freetype-dev libpng-dev libjpeg-turbo-dev && \
  docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
  NPROC=$(grep -c ^processor /proc/cpuinfo 2>/dev/null || 1) && \
  docker-php-ext-install -j$(nproc) gd && \
  apk del --no-cache freetype-dev libpng-dev libjpeg-turbo-dev
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
  && pecl install xdebug-3.0.0 \
  && docker-php-ext-enable xdebug \
  && apk del -f .build-deps
RUN apk add icu-dev
RUN docker-php-ext-configure intl && docker-php-ext-install intl
RUN docker-php-ext-install pdo_mysql
COPY --from=composer /usr/bin/composer /usr/bin/composer
CMD composer install ;  php-fpm

# Install selected extensions and other stuff
#RUN apt-get update \
##    && apt-get -y --no-install-recommends install php8.0-mysql php8.0-gd php8.0-imagick php8.0-intl php8.0-yaml php8.0-xdebug \
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

## Copy script used at docker-start
#COPY docker-init.sh /usr/local/bin/docker-init
