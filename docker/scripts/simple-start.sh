#!/bin/bash

# Ultra-simple startup script - just start PHP-FPM
# No complex initialization that can hang

echo "🚀 Simple PHP-FPM Startup..."

# Basic directory creation only
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# Basic permissions
chmod 777 /var/www/html/storage 2>/dev/null || true
chmod 777 /var/www/html/bootstrap/cache 2>/dev/null || true

echo "✅ Starting PHP-FPM immediately..."

# Start PHP-FPM directly
exec php-fpm --nodaemonize --force-stderr
