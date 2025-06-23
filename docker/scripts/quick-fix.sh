#!/bin/bash

# Quick 502 Fix Script
# This script fixes common 502 issues without rebuilding containers

echo "🔧 Quick 502 Fix - Starting..."

# Function to wait for service
wait_for_service() {
    local service=$1
    local port=$2
    local max_attempts=30
    local attempt=1
    
    echo "⏳ Waiting for $service on port $port..."
    
    while [ $attempt -le $max_attempts ]; do
        if nc -z localhost $port 2>/dev/null; then
            echo "✅ $service is ready on port $port"
            return 0
        fi
        echo "   Attempt $attempt/$max_attempts - $service not ready yet..."
        sleep 2
        attempt=$((attempt + 1))
    done
    
    echo "❌ $service failed to start on port $port after $max_attempts attempts"
    return 1
}

# Stop all containers
echo "🛑 Stopping all containers..."
docker-compose down

# Remove any orphaned containers
echo "🧹 Cleaning up orphaned containers..."
docker container prune -f

# Start database and Redis first
echo "🗄️ Starting database and Redis..."
docker-compose up -d mysql redis

# Wait for database
wait_for_service "MySQL" 3306

# Wait for Redis
wait_for_service "Redis" 6379

# Start the app container
echo "🚀 Starting app container..."
docker-compose up -d app

# Wait for app to be ready
echo "⏳ Waiting for app container to initialize..."
sleep 30

# Check if PHP-FPM is running
echo "🔍 Checking PHP-FPM status..."
if docker exec qode_blog_app pgrep -f "php-fpm" > /dev/null; then
    echo "✅ PHP-FPM is running"
else
    echo "❌ PHP-FPM is not running, restarting app container..."
    docker restart qode_blog_app
    sleep 20
fi

# Start nginx
echo "🌐 Starting nginx..."
docker-compose up -d nginx

# Start remaining services
echo "📧 Starting remaining services..."
docker-compose up -d mailhog phpmyadmin queue scheduler redis-commander

# Final health check
echo "🏥 Performing health check..."
sleep 10

# Test the API
echo "🧪 Testing API endpoint..."
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/api/blogs | grep -q "200"; then
    echo "✅ API is responding correctly!"
    echo ""
    echo "🎉 502 Fix Complete! Your services are now running:"
    echo "   📱 Main App: http://localhost:8000"
    echo "   📊 Redis Commander: http://localhost:8081"
    echo "   📧 MailHog: http://localhost:8025"
    echo "   🗄️ phpMyAdmin: http://localhost:8080"
    echo ""
else
    echo "⚠️ API is not responding correctly. Checking logs..."
    echo "App container logs:"
    docker logs qode_blog_app --tail=10
    echo ""
    echo "Nginx container logs:"
    docker logs qode_blog_nginx --tail=10
fi

echo "🔧 Quick fix script completed!"
