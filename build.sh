#!/bin/bash

# Install Composer if not available
if ! command -v composer &> /dev/null; then
    echo "Installing Composer..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    php -r "unlink('composer-setup.php');"
fi

# Install PHP dependencies
echo "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

# Verify installation
if [ -f "vendor/autoload.php" ]; then
    echo "✅ PHPMailer installed successfully"
else
    echo "❌ PHPMailer installation failed"
    exit 1
fi
