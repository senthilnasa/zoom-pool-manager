FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    curl \
    git \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    gmp-dev \
    icu-dev \
    oniguruma-dev \
    mariadb-client \
    rsync \
    linux-headers \
    $PHPIZE_DEPS

# Install PHP extensions required by Laravel & ZPM
RUN docker-php-ext-install \
    pdo_mysql \
    bcmath \
    mbstring \
    xml \
    zip \
    gmp \
    intl \
    opcache \
    pcntl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create non-root user for security
RUN addgroup -g 1000 zpm && adduser -u 1000 -G zpm -s /bin/sh -D zpm
USER zpm

EXPOSE 9000
CMD ["php-fpm"]
