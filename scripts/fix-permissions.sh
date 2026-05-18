#!/bin/bash

echo "🔧 Fixing Laravel permissions..."

# Set ownership to current user
sudo chown -R $USER:$USER .

# Set base permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Laravel specific directories
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chmod -R 775 storage/logs
chmod -R 775 storage/framework

# Create and set invoices directories
mkdir -p storage/app/private/invoices
mkdir -p storage/app/public/invoices
mkdir -p storage/invoices
chmod -R 777 storage/app/private/invoices
chmod -R 777 storage/app/public/invoices
chmod -R 777 storage/invoices

# Make scripts executable
chmod +x scripts/*.sh

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear

echo "✅ Permissions fixed successfully!"
