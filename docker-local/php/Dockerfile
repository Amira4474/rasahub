FROM composer:2.10.3 AS composer

FROM php:8.5.11-fpm-trixie

ARG HOST_UID=1000
ARG HOST_GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl bcmath zip gd exif pcntl \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

# Run as a user matching the host user so nothing in the bind-mounted project
# ends up owned by root. vendor/ is pre-created so its named volume starts out
# owned by this user.
RUN groupadd --non-unique --gid "${HOST_GID}" rasahub \
    && useradd --non-unique --uid "${HOST_UID}" --gid "${HOST_GID}" --create-home --shell /bin/bash rasahub \
    && mkdir -p /var/www/html/vendor \
    && chown -R rasahub:rasahub /var/www/html

USER rasahub
WORKDIR /var/www/html
