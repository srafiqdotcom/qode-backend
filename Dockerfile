# Simple Laravel Docker Image - Fast Build
FROM php:8.2-fpm

WORKDIR /var/www/html

# Install only essential packages in one layer
RUN apt-get update && apt-get install -y \
    libpng-dev libzip-dev \
    && docker-php-ext-install pdo_mysql gd zip \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy app and install dependencies
COPY . /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Simple permissions - no complex operations
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views storage/app/public bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache

EXPOSE 9000

# Default command - can be overridden by docker-compose
CMD ["php-fpm"]
