FROM php:5.6-apache
COPY docker-php-pecl-install /usr/local/bin/

RUN echo "deb http://archive.debian.org/debian/ stretch main" > /etc/apt/sources.list && \
    echo "deb-src http://archive.debian.org/debian/ stretch main" >> /etc/apt/sources.list && \
    apt-get -o Acquire::Check-Valid-Until=false update


RUN apt-get install -y \
        git \
        unzip \
        wget \
        libzip-dev \
        libicu-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev

RUN docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        intl \
        zip \
        calendar \
        opcache && \
    docker-php-ext-configure gd \
        --with-freetype-dir=/usr/include/ \
        --with-jpeg-dir=/usr/include/ \
        --with-webp-dir=/usr/include/ && \
    docker-php-ext-install -j$(nproc) gd

RUN pecl update-channels && \
    docker-php-ext-enable opcache


# Setup and install composer
RUN wget https://getcomposer.org/download/1.2.4/composer.phar -O /usr/local/bin/composer && \
        chmod a+rx /usr/local/bin/composer

RUN a2enmod rewrite
RUN usermod -u 1000 www-data
COPY config/php.ini /usr/local/etc/php/
COPY config/vhost/vhost.conf /etc/apache2/sites-enabled/
