#!/bin/bash

# Laravel Blog Backend Container Initialization Script
# This script handles all the initialization tasks for the Laravel application inside containers

set -e  # Exit on any error

echo "🚀 Starting Laravel Blog Backend Container Initialization..."

# Function to wait for database
wait_for_database() {
    echo "⏳ Waiting for database connection..."
    local max_attempts=30
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if php artisan migrate:status >/dev/null 2>&1; then
            echo "✅ Database connection established!"
            return 0
        fi
        echo "Database not ready, attempt $attempt/$max_attempts, waiting 2 seconds..."
        sleep 2
        ((attempt++))
    done
    
    echo "❌ Failed to connect to database after $max_attempts attempts"
    exit 1
}

# Function to wait for Redis
wait_for_redis() {
    echo "⏳ Waiting for Redis connection..."
    local max_attempts=15
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if php artisan tinker --execute="Redis::ping(); echo 'Redis connected';" >/dev/null 2>&1; then
            echo "✅ Redis connection established!"
            return 0
        fi
        echo "Redis not ready, attempt $attempt/$max_attempts, waiting 2 seconds..."
        sleep 2
        ((attempt++))
    done
    
    echo "❌ Failed to connect to Redis after $max_attempts attempts"
    exit 1
}

# Set proper permissions - simplified to avoid hanging
echo "🔧 Setting proper permissions..."
# **qode** Quick permission setup without recursive operations that can hang
chmod 777 /var/www/html/storage 2>/dev/null || true
chmod 777 /var/www/html/bootstrap/cache 2>/dev/null || true
mkdir -p /var/www/html/storage/logs /var/www/html/storage/framework/cache /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views /var/www/html/storage/app/public

# Create storage link if it doesn't exist
echo "🔗 Creating storage link..."
if [ ! -L /var/www/html/public/storage ]; then
    php artisan storage:link
    echo "Storage link created"
else
    echo "Storage link already exists"
fi

# Wait for dependencies
wait_for_database
wait_for_redis

# Generate application key if not exists
echo "🔑 Checking application key..."
if grep -q "APP_KEY=$" /var/www/html/.env 2>/dev/null || [ ! -f /var/www/html/.env ]; then
    echo "Generating application key..."
    php artisan key:generate --force
else
    echo "Application key already exists"
fi

# Run database migrations
echo "📊 Running database migrations..."
if php artisan migrate --force 2>/dev/null; then
    echo "✅ Database migrations completed successfully"
else
    echo "⚠️  Migration failed, trying to reset and migrate..."
    php artisan migrate:reset --force 2>/dev/null || true
    php artisan migrate --force
    echo "✅ Database reset and migrated successfully"
fi

# Generate Passport keys
echo "🔐 Setting up Laravel Passport..."
if [ ! -f /var/www/html/storage/oauth-private.key ] || [ ! -f /var/www/html/storage/oauth-public.key ]; then
    echo "Generating Passport encryption keys..."
    php artisan passport:keys --force
    
    # Set proper permissions for Passport keys
    chown www-data:www-data /var/www/html/storage/oauth-*.key 2>/dev/null || true
    chmod 644 /var/www/html/storage/oauth-*.key 2>/dev/null || true
    echo "Passport keys generated and permissions set"
else
    echo "Passport keys already exist"
    # Ensure proper permissions on existing keys
    chown www-data:www-data /var/www/html/storage/oauth-*.key 2>/dev/null || true
    chmod 644 /var/www/html/storage/oauth-*.key 2>/dev/null || true
fi

# Install Passport (create clients)
echo "📱 Installing Passport clients..."
# Check if personal access client exists
CLIENT_EXISTS=$(php artisan tinker --execute="echo DB::table('oauth_clients')->where('personal_access_client', 1)->exists() ? 'exists' : 'not_exists';" 2>/dev/null | grep -o "exists\|not_exists" || echo "not_exists")

if [ "$CLIENT_EXISTS" != "exists" ]; then
    echo "Creating Passport personal access client..."
    php artisan passport:client --personal --name="Laravel Blog Personal Access Client" --no-interaction
    echo "Passport personal access client created"
else
    echo "Passport personal access client already exists"
fi

# Clear and cache configurations
echo "🧹 Clearing and caching configurations..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Cache configurations for better performance
echo "⚡ Caching configurations..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Seed database if needed (only in development)
if [ "${APP_ENV:-local}" = "local" ] && [ "${SEED_DATABASE:-false}" = "true" ]; then
    echo "🌱 Seeding database..."
    php artisan db:seed --force
fi

# Test connections
echo "🔍 Testing connections..."
php artisan tinker --execute="Redis::set('setup_test', 'success'); echo 'Redis test: ' . Redis::get('setup_test');" >/dev/null 2>&1 && echo "✅ Redis test passed" || echo "❌ Redis test failed"
php artisan tinker --execute="echo 'Database test: ' . (DB::connection()->getPdo() ? 'Connected' : 'Failed');" >/dev/null 2>&1 && echo "✅ Database test passed" || echo "❌ Database test failed"

echo "✅ Laravel Blog Backend container initialization completed successfully!"
echo "🎯 Container is ready!"

# Final verification before starting services
echo "🔍 Final system verification..."

# Verify PHP-FPM configuration
if ! php-fpm -t 2>/dev/null; then
    echo "❌ PHP-FPM configuration test failed"
    exit 1
fi

# Verify Laravel is properly configured
if ! php artisan --version >/dev/null 2>&1; then
    echo "❌ Laravel application verification failed"
    exit 1
fi

echo "✅ All verifications passed!"

# Start the appropriate service based on container role
case "${CONTAINER_ROLE:-app}" in
    "app")
        echo "🚀 Starting PHP-FPM..."
        # Start PHP-FPM in foreground mode
        exec php-fpm --nodaemonize --force-stderr
        ;;
    "queue")
        echo "🔄 Starting queue worker..."
        exec php artisan queue:work redis --queue=emails,default --tries=3 --timeout=60 --sleep=3 --verbose
        ;;
    "scheduler")
        echo "⏰ Starting scheduler..."
        # Install cron if not present
        if ! command -v cron &> /dev/null; then
            apt-get update && apt-get install -y cron
        fi

        # Start cron in the background
        cron

        # Keep the container running and run the scheduler every minute
        while true; do
            php artisan schedule:run
            sleep 60
        done
        ;;
    *)
        echo "🚀 Starting PHP-FPM (default)..."
        exec php-fpm --nodaemonize --force-stderr
        ;;
esac
