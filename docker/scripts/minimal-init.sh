#!/bin/bash

# Minimal Laravel initialization script - designed to never hang
# This script focuses on getting PHP-FPM running quickly

set -e

echo "🚀 Minimal Laravel Initialization Starting..."

# Basic directory creation
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/app/public
mkdir -p /var/www/html/bootstrap/cache

# Quick permissions - no recursive operations
chmod 777 /var/www/html/storage 2>/dev/null || true
chmod 777 /var/www/html/bootstrap/cache 2>/dev/null || true

# Generate app key if not exists
if [ ! -f /var/www/html/.env ] || ! grep -q "APP_KEY=" /var/www/html/.env || grep -q "APP_KEY=$" /var/www/html/.env; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force 2>/dev/null || true
fi

# Create storage link if not exists
if [ ! -L /var/www/html/public/storage ]; then
    echo "🔗 Creating storage link..."
    php artisan storage:link 2>/dev/null || true
fi

# Cache config for performance
echo "⚡ Caching configuration..."
php artisan config:cache 2>/dev/null || true

echo "✅ Minimal initialization completed!"

# Start the appropriate service based on container role
case "${CONTAINER_ROLE:-app}" in
    "app")
        echo "🚀 Starting PHP-FPM..."
        exec php-fpm --nodaemonize --force-stderr
        ;;
    "queue")
        echo "🔄 Starting queue worker..."
        exec php artisan queue:work redis --queue=emails,default --tries=3 --timeout=60 --sleep=3 --verbose
        ;;
    "scheduler")
        echo "⏰ Starting scheduler..."
        while true; do
            php artisan schedule:run 2>/dev/null || true
            sleep 60
        done
        ;;
    *)
        echo "🚀 Starting PHP-FPM (default)..."
        exec php-fpm --nodaemonize --force-stderr
        ;;
esac
