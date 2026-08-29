FROM php:8.3-fpm-alpine

# Install build dependencies and runtime libraries for PHP extensions
RUN apk add --no-cache \
    linux-headers \
    $PHPIZE_DEPS \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    curl \
    git \
    unzip \
    bash \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        intl \
        mbstring \
        opcache \
        bcmath \
    && apk del --no-network $PHPIZE_DEPS linux-headers

# Install Composer for dependency management (if needed later)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Custom PHP and FPM configuration
COPY php.ini /usr/local/etc/php/conf.d/custom.ini
COPY php-fpm.d/www.conf /usr/local/etc/php-fpm.d/zz-docker.conf

WORKDIR /var/www

# Expose PHP-FPM port
EXPOSE 9000

CMD ["php-fpm", "-F"]
