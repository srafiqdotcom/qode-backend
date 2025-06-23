#!/bin/bash

# Health check script for Laravel application
# This script ensures PHP-FPM is running and Laravel is responding

set -e

# Check if PHP-FPM is running
if ! pgrep -f "php-fpm: master process" > /dev/null; then
    echo "PHP-FPM master process not found"
    exit 1
fi

# Check if PHP-FPM is listening on port 9000
if ! netstat -ln 2>/dev/null | grep -q ":9000 "; then
    echo "PHP-FPM not listening on port 9000"
    exit 1
fi

# Check if Laravel application responds
if ! php artisan --version >/dev/null 2>&1; then
    echo "Laravel application not responding"
    exit 1
fi

# Check database connection
if ! php artisan tinker --execute="DB::connection()->getPdo(); echo 'DB OK';" >/dev/null 2>&1; then
    echo "Database connection failed"
    exit 1
fi

# Check Redis connection
if ! php artisan tinker --execute="Redis::ping(); echo 'Redis OK';" >/dev/null 2>&1; then
    echo "Redis connection failed"
    exit 1
fi

echo "Health check passed"
exit 0
