#!/bin/bash

# Laravel Blog Production Deployment Script
set -e

echo "🚀 Deploying Laravel Blog to production..."

# Check if we're in production mode
if [ "$APP_ENV" != "production" ]; then
    echo "⚠️  Warning: APP_ENV is not set to 'production'"
    read -p "Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Pull latest changes
echo "📥 Pulling latest changes..."
git pull origin main

# Build production containers
echo "🏗️  Building production containers..."
docker-compose -f docker-compose.yml build --no-cache

# Stop existing containers
echo "🛑 Stopping existing containers..."
docker-compose down

# Start new containers
echo "🚀 Starting new containers..."
docker-compose up -d

# Wait for services to be ready
echo "⏳ Waiting for services to be ready..."
sleep 30

# Run migrations
echo "🗄️  Running database migrations..."
docker-compose exec app php artisan migrate --force

# Clear all caches
echo "🧹 Clearing caches..."
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear

# Optimize for production
echo "⚡ Optimizing for production..."
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache

# Restart queue workers
echo "🔄 Restarting queue workers..."
docker-compose restart queue

# Run health checks
echo "🏥 Running health checks..."
sleep 10

# Check if app is responding
if curl -f http://localhost:8000/health > /dev/null 2>&1; then
    echo "✅ Application is healthy"
else
    echo "❌ Application health check failed"
    echo "📋 Recent logs:"
    docker-compose logs --tail=20 app
    exit 1
fi

# Check database connection
if docker-compose exec app php artisan migrate:status > /dev/null 2>&1; then
    echo "✅ Database connection is healthy"
else
    echo "❌ Database connection failed"
    exit 1
fi

# Check Redis connection
if docker-compose exec app php artisan tinker --execute="Redis::ping()" | grep -q "PONG"; then
    echo "✅ Redis connection is healthy"
else
    echo "❌ Redis connection failed"
    exit 1
fi

echo ""
echo "🎉 Deployment completed successfully!"
echo ""
echo "📊 Container status:"
docker-compose ps
echo ""
echo "📍 Application URLs:"
echo "   🌐 Main App: http://localhost:8000"
echo "   📧 MailHog: http://localhost:8025"
echo ""
