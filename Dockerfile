# Use a PHP-FPM + Nginx base image
FROM richarvey/nginx-php-fpm:1.22

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock* ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy application code
COPY . .

# Make sure files are owned by www-data
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

CMD ["/start.sh"]
