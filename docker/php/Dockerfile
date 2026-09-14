FROM php:8.5-cli

RUN apt-get update \
    && apt-get install --yes --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
