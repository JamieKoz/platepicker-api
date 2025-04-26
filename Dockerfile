FROM php:8.1-apache
# Enable required Apache modules
RUN a2enmod rewrite ssl
# Install PHP extensions and dependencies
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    zip \
    unzip \
    curl \
    vim \
    rsyslog \
 	certbot \
    python3-certbot-apache \
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
# Expose port 80 for Apache
EXPOSE 80
# Start Apache

RUN mkdir -p /etc/apache2/ssl
RUN a2ensite default-ssl
# Expose both HTTP and HTTPS ports
EXPOSE 80 443
CMD ["apache2-foreground"]
