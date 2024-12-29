FROM php:8.1-apache

# Enable required Apache modules
RUN a2enmod rewrite

# Install PHP extensions and dependencies
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    zip \
    unzip \
    curl \
    rsyslog \
    && docker-php-ext-install pdo pdo_sqlite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure Apache logging
RUN mkdir -p /var/log/apache2 \
    && touch /var/log/apache2/error.log /var/log/apache2/access.log \
    && chown -R www-data:www-data /var/log/apache2

# Update Apache configuration
RUN sed -i 's#ErrorLog .*#ErrorLog /var/log/apache2/error.log#' /etc/apache2/apache2.conf \
    && sed -i 's#CustomLog .*#CustomLog /var/log/apache2/access.log combined#' /etc/apache2/apache2.conf

# Configure PHP error logging
RUN echo "error_log = /var/log/apache2/php_errors.log" >> /usr/local/etc/php/conf.d/docker-php-custom.ini

# Configure Apache DocumentRoot and settings
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && echo "<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n</Directory>" >> /etc/apache2/apache2.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Create necessary directories and set permissions before composer install
RUN mkdir -p bootstrap/cache storage/app/public \
    && chown -R www-data:www-data bootstrap/cache storage \
    && chmod -R 775 bootstrap/cache storage

# Set up environment file
COPY .env.production .env

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Final permission setup and storage link
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/log/apache2 /var/www/html/public \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/log/apache2 /var/www/html/public \
    && rm -f /var/www/html/public/storage \
    && cd /var/www/html && php artisan storage:link

# Expose port 80 for Apache
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
