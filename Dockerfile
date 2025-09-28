# Multi-stage Dockerfile for PHP + Nginx on Render
# Stage 1: Composer dependencies
FROM composer:2 AS composer
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Stage 2: PHP-FPM setup
FROM php:8.3-fpm-alpine AS php-fpm
# Install necessary system dependencies and PHP extensions
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql \
    && apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis

# Copy Composer dependencies from Stage 1
COPY --from=composer /app/vendor /var/www/html/vendor

# Stage 3: Final Nginx + PHP-FPM image
FROM nginx:alpine AS runtime
# Install PHP-FPM and required PHP extensions
RUN apk add --no-cache php83-fpm php83-mysqli php83-pdo php83-pdo_mysql php83-openssl php83-json php83-mbstring

# Copy PHP-FPM configuration
RUN mkdir -p /etc/php83/php-fpm.d
COPY <<EOF /etc/php83/php-fpm.d/www.conf
[www]
user = nginx
group = nginx
listen = 127.0.0.1:9000
listen.owner = nginx
listen.group = nginx
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
catch_workers_output = yes
EOF

# Copy Nginx configuration with CORS headers and explicit PHP handling
COPY <<EOF /etc/nginx/conf.d/default.conf
server {
    listen 80;
    server_name localhost;
    root /var/www/html;
    index index.php index.html;

    # Add CORS headers
    add_header Access-Control-Allow-Origin "*" always;
    add_header Access-Control-Allow-Methods "POST, OPTIONS" always;
    add_header Access-Control-Allow-Headers "Content-Type" always;

    # Handle PHP files explicitly
    location ~ \.php$ {
        try_files $uri =404;  # Simplified to return 404 if file doesn't exist
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME "$document_root$fastcgi_script_name";
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
    }

    # Static files
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Security: Deny hidden files
    location ~ /\. {
        deny all;
    }
}
EOF

# Test Nginx configuration during build
RUN nginx -t

# Copy app code (including your PHP script)
COPY --from=php-fpm /var/www/html /var/www/html
COPY . /var/www/html

# Set permissions for web server (reinforced for Nginx user)
RUN chown -R nginx:nginx /var/www/html && \
    chmod -R 755 /var/www/html && \
    find /var/www/html -type f -exec chmod 644 {} \; && \
    find /var/www/html -type d -exec chmod 755 {} \; && \
    mkdir -p /var/log/php83 && \
    chown -R nginx:nginx /var/log/php83 /run

# Expose port 80
EXPOSE 80

# Start PHP-FPM and Nginx in foreground
CMD ["/bin/sh", "-c", "php-fpm83 -F -O & nginx -g 'daemon off;'"]
