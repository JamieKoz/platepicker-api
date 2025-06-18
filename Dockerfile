FROM php:8.1-fpm

# Install PHP extensions and dependencies
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libpq-dev \
    zip \
    unzip \
    curl \
    vim \
    git \
    && docker-php-ext-install pdo pdo_sqlite pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure PHP logging
RUN echo "error_log = /var/log/php-fpm/php_errors.log" >> /usr/local/etc/php/conf.d/docker-php-custom.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Set up environment file
COPY .env.production .env

# Create all necessary directories and set initial permissions
RUN mkdir -p bootstrap/cache \
    && mkdir -p storage/logs \
    && mkdir -p storage/framework/{cache,sessions,views} \
    && mkdir -p storage/app/public \
    && touch storage/logs/laravel.log \
    && chown -R www-data:www-data . \
    && chmod -R 775 storage bootstrap/cache \
    && chmod 664 storage/logs/laravel.log \
    && chmod 775 database \
    && chmod 664 database/database.sqlite

# Install PHP dependencies
RUN composer require league/flysystem-aws-s3-v3 \
    && composer install --no-dev --optimize-autoloader

# Final permission setup and storage link
RUN php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Expose port 9000 for PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]
