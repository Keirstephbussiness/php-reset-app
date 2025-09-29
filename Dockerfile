# Stage 1: Build dependencies
FROM php:8.1.30-apache-bullseye AS builder

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    libxml2-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    bcmath \
    curl \
    exif \
    fileinfo \
    gd \
    intl \
    mbstring \
    pcntl \
    pdo \
    pdo_mysql \
    tokenizer \
    xml \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* || (echo "Failed to install system dependencies" && exit 1)

# Install Composer
RUN curl -sS https://getcomposer.org/installer -o composer-installer.php && \
    php composer-installer.php -- --install-dir=/usr/local/bin --filename=composer --version=2.7.9 && \
    rm composer-installer.php || (echo "Composer installation failed" && exit 1)

# Verify Composer installation
RUN composer --version || (echo "Composer not installed correctly" && exit 1)

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Verify composer files
RUN [ -f composer.json ] && [ -f composer.lock ] || (echo "composer.json or composer.lock missing" && exit 1)

# Install PHP dependencies with more verbosity and unlimited memory
RUN COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --verbose || (echo "Composer install failed" && exit 1)

# Stage 2: Final image
FROM php:8.1.30-apache-bullseye

# Enable Apache modules
RUN a2enmod rewrite headers || (echo "Failed to enable Apache modules" && exit 1)

# Install runtime dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    libxml2-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    bcmath \
    curl \
    exif \
    fileinfo \
    gd \
    intl \
    mbstring \
    pcntl \
    pdo \
    pdo_mysql \
    tokenizer \
    xml \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* || (echo "Failed to install runtime dependencies" && exit 1)

# Copy application from builder stage
COPY --from=builder /var/www/html /var/www/html

# Copy rest of the application
COPY . .

# Create storage directory and set permissions
RUN mkdir -p /var/www/html/storage && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 775 /var/www/html/storage || (echo "Failed to set permissions" && exit 1)

# Use non-root user
USER www-data

# Expose port
EXPOSE 80

# Healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s \
    CMD curl -f http://localhost/ || exit 1

# Start Apache
CMD ["apache2-foreground"]
