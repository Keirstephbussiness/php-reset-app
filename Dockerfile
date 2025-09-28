# Use a valid PHP-FPM + Nginx base image (latest stable: PHP 8.2.7, Nginx 1.24, Alpine 3.18)
FROM richarvey/nginx-php-fpm:1.24-r6

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (for better caching)
COPY composer.json composer.lock* ./

# Install PHP dependencies (no-dev for production)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy application code
COPY . .

# Ensure files are owned by www-data (Nginx/PHP user)
RUN chown -R www-data:www-data /var/www/html

# Expose port 80 for web traffic
EXPOSE 80

# The base image provides the start script (/start.sh) for Nginx + PHP-FPM
CMD ["/start.sh"]
