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
RUN apk add --update --no-cache autoconf g++ imagemagick imagemagick-dev libtool make pcre-dev
RUN pecl install imagick
RUN docker-php-ext-enable imagick

# GD extension needed for html2pdf
RUN apk add --no-cache freetype libpng libjpeg-turbo freetype-dev libpng-dev libjpeg-turbo-dev libwebp-dev && \
  docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
  NPROC=$(grep -c ^processor /proc/cpuinfo 2>/dev/null || 1) && \
  docker-php-ext-install -j$(nproc) gd && \
  apk del --no-cache freetype-dev libpng-dev libjpeg-turbo-dev

# XDebug extension
#RUN apk add --update linux-headers
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

# NODE & NPM install
ENV NODE_VERSION 22.12.0

RUN addgroup -g 1001 node \
    && adduser -u 1001 -G node -s /bin/sh -D node \
    && apk add --no-cache \
        libstdc++ \
    && apk add --no-cache --virtual .build-deps \
        curl \
    && ARCH= OPENSSL_ARCH='linux*' && alpineArch="$(apk --print-arch)" \
      && case "${alpineArch##*-}" in \
        x86_64) ARCH='x64' CHECKSUM="43532120bad06cdea17c2ffba81ebfcff4611532a3569ef996faa39aadcbc759" OPENSSL_ARCH=linux-x86_64;; \
        x86) OPENSSL_ARCH=linux-elf;; \
        aarch64) OPENSSL_ARCH=linux-aarch64;; \
        arm*) OPENSSL_ARCH=linux-armv4;; \
        ppc64le) OPENSSL_ARCH=linux-ppc64le;; \
        s390x) OPENSSL_ARCH=linux-s390x;; \
        *) ;; \
      esac \
  && if [ -n "${CHECKSUM}" ]; then \
    set -eu; \
    curl -fsSLO --compressed "https://unofficial-builds.nodejs.org/download/release/v$NODE_VERSION/node-v$NODE_VERSION-linux-$ARCH-musl.tar.xz"; \
    echo "$CHECKSUM  node-v$NODE_VERSION-linux-$ARCH-musl.tar.xz" | sha256sum -c - \
      && tar -xJf "node-v$NODE_VERSION-linux-$ARCH-musl.tar.xz" -C /usr/local --strip-components=1 --no-same-owner \
      && ln -s /usr/local/bin/node /usr/local/bin/nodejs; \
  else \
    echo "Building from source" \
    # backup build
    && apk add --no-cache --virtual .build-deps-full \
        binutils-gold \
        g++ \
        gcc \
        gnupg \
        libgcc \
        linux-headers \
        make \
        python3 \
        py-setuptools \
    # use pre-existing gpg directory, see https://github.com/nodejs/docker-node/pull/1895#issuecomment-1550389150
    && export GNUPGHOME="$(mktemp -d)" \
    # gpg keys listed at https://github.com/nodejs/node#release-keys
    && for key in \
      C0D6248439F1D5604AAFFB4021D900FFDB233756 \
      DD792F5973C6DE52C432CBDAC77ABFA00DDBF2B7 \
      CC68F5A3106FF448322E48ED27F5E38D5B0A215F \
      8FCCA13FEF1D0C2E91008E09770F7A9A5AE15600 \
      890C08DB8579162FEE0DF9DB8BEAB4DFCF555EF4 \
      C82FA3AE1CBEDC6BE46B9360C43CEC45C17AB93C \
      108F52B48DB57BB0CC439B2997B01419BD92F80A \
      A363A499291CBBC940DD62E41F10027AF002F8B0 \
    ; do \
      gpg --batch --keyserver hkps://keys.openpgp.org --recv-keys "$key" || \
      gpg --batch --keyserver keyserver.ubuntu.com --recv-keys "$key" ; \
    done \
    && curl -fsSLO --compressed "https://nodejs.org/dist/v$NODE_VERSION/node-v$NODE_VERSION.tar.xz" \
    && curl -fsSLO --compressed "https://nodejs.org/dist/v$NODE_VERSION/SHASUMS256.txt.asc" \
    && gpg --batch --decrypt --output SHASUMS256.txt SHASUMS256.txt.asc \
    && gpgconf --kill all \
    && rm -rf "$GNUPGHOME" \
    && grep " node-v$NODE_VERSION.tar.xz\$" SHASUMS256.txt | sha256sum -c - \
    && tar -xf "node-v$NODE_VERSION.tar.xz" \
    && cd "node-v$NODE_VERSION" \
    && ./configure \
    && make -j$(getconf _NPROCESSORS_ONLN) V= \
    && make install \
    && apk del .build-deps-full \
    && cd .. \
    && rm -Rf "node-v$NODE_VERSION" \
    && rm "node-v$NODE_VERSION.tar.xz" SHASUMS256.txt.asc SHASUMS256.txt; \
  fi \
  && rm -f "node-v$NODE_VERSION-linux-$ARCH-musl.tar.xz" \
  # Remove unused OpenSSL headers to save ~34MB. See this NodeJS issue: https://github.com/nodejs/node/issues/46451
  && find /usr/local/include/node/openssl/archs -mindepth 1 -maxdepth 1 ! -name "$OPENSSL_ARCH" -exec rm -rf {} \; \
  && apk del .build-deps \
  # smoke tests
  && node --version \
  && npm --version

ENV YARN_VERSION 1.22.22

RUN apk add --no-cache --virtual .build-deps-yarn curl gnupg tar \
  # use pre-existing gpg directory, see https://github.com/nodejs/docker-node/pull/1895#issuecomment-1550389150
  && export GNUPGHOME="$(mktemp -d)" \
  && for key in \
    6A010C5166006599AA17F08146C2130DFD2497F5 \
  ; do \
    gpg --batch --keyserver hkps://keys.openpgp.org --recv-keys "$key" || \
    gpg --batch --keyserver keyserver.ubuntu.com --recv-keys "$key" ; \
  done \
  && curl -fsSLO --compressed "https://yarnpkg.com/downloads/$YARN_VERSION/yarn-v$YARN_VERSION.tar.gz" \
  && curl -fsSLO --compressed "https://yarnpkg.com/downloads/$YARN_VERSION/yarn-v$YARN_VERSION.tar.gz.asc" \
  && gpg --batch --verify yarn-v$YARN_VERSION.tar.gz.asc yarn-v$YARN_VERSION.tar.gz \
  && gpgconf --kill all \
  && rm -rf "$GNUPGHOME" \
  && mkdir -p /opt \
  && tar -xzf yarn-v$YARN_VERSION.tar.gz -C /opt/ \
  && ln -s /opt/yarn-v$YARN_VERSION/bin/yarn /usr/local/bin/yarn \
  && ln -s /opt/yarn-v$YARN_VERSION/bin/yarnpkg /usr/local/bin/yarnpkg \
  && rm yarn-v$YARN_VERSION.tar.gz.asc yarn-v$YARN_VERSION.tar.gz \
  && apk del .build-deps-yarn \
  # smoke test
  && yarn --version \
  && rm -rf /tmp/*
# Copy script used at docker-start
COPY docker-init.sh /usr/local/bin/docker-init
RUN chmod 755 /usr/local/bin/docker-init

USER thelia
