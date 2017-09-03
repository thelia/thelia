FROM php:5.6-apache
COPY docker-php-pecl-install /usr/local/bin/

RUN apt-get update && apt-get install -y \
 libfreetype6-dev \
 libjpeg62-turbo-dev \
 openssl \
 libpng12-dev \
 libicu-dev \
 git \
 zip \
 libzip-dev \
    && docker-php-ext-install intl pdo_mysql openssl mbstring zip calendar \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install gd \
    && docker-php-pecl-install xdebug-2.3.3

# Setup and install composer
RUN curl -o /tmp/composer-setup.php https://getcomposer.org/installer \
  && curl -o /tmp/composer-setup.sig https://composer.github.io/installer.sig \
  && php -r "if (hash('SHA384', file_get_contents('/tmp/composer-setup.php')) !== trim(file_get_contents('/tmp/composer-setup.sig'))) { unlink('/tmp/composer-setup.php'); echo 'Invalid installer' . PHP_EOL; exit(1); }" \
  && php /tmp/composer-setup.php \
  && chmod +x composer.phar \
  && mv composer.phar /usr/local/bin/composer

RUN a2enmod rewrite
RUN usermod -u 1000 www-data
COPY config/php.ini /usr/local/etc/php/
COPY config/vhost/vhost.conf /etc/apache2/sites-enabled/
