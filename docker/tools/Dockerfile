FROM debian:jessie

RUN apt-get update \
 && apt-get install --no-install-recommends -y \
    php5-cli \
    php5-curl \
    php5-json \
    php5-intl \
 && apt-get clean \
 && rm -r /var/lib/apt/lists/*