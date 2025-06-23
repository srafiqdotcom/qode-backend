#!/bin/bash

# Laravel Blog Docker Setup Script
set -e

echo "🚀 Setting up Laravel Blog with Docker..."

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Copy environment file
if [ ! -f .env ]; then
    echo "📋 Copying Docker environment file..."
    cp .env.docker .env
    echo "✅ Environment file created."
else
    echo "⚠️  .env file already exists. Skipping copy."
fi

# Stop any existing containers
echo "🛑 Stopping any existing containers..."
docker-compose down --remove-orphans

# Build containers
echo "🏗️  Building Docker containers..."
docker-compose build --no-cache

echo "🚀 Starting containers..."
docker-compose up -d

# Wait for all services to be ready
echo "⏳ Waiting for all services to initialize..."
echo "   This includes database setup, migrations, Passport installation, and more..."
sleep 45

# Check container status
echo "📊 Checking container status..."
docker-compose ps

# Test the application
echo "🔍 Testing application..."
sleep 10

# Test API endpoint
if curl -f -s http://localhost:8000/api/blogs >/dev/null 2>&1; then
    echo "✅ API is responding correctly"
else
    echo "⚠️  API might still be initializing, check logs if needed"
fi

# Show logs for troubleshooting if needed
echo "📋 Recent application logs:"
docker-compose logs --tail=10 app

# Seed database (optional)
read -p "🌱 Do you want to seed the database with sample data? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "🌱 Seeding database..."
    docker-compose exec app php artisan db:seed --force
fi

echo ""
echo "🎉 Setup complete!"
echo ""
echo "📍 Your application is now running at:"
echo "   🌐 Main App: http://localhost:8000"
echo "   📧 MailHog: http://localhost:8025 (Email testing)"
echo "   🗄️  phpMyAdmin: http://localhost:8080 (Database management)"
echo ""
echo "🔧 All services are automatically configured:"
echo "   ✅ Laravel Passport (OAuth2) - Keys generated and clients created"
echo "   ✅ Queue Workers - Processing emails and jobs"
echo "   ✅ Redis Cache - Caching and session storage"
echo "   ✅ Email System - MailHog for email testing"
echo "   ✅ Database - MySQL with migrations applied"
echo "   ✅ Scheduler - Laravel cron jobs"
echo ""
echo "🧪 Test the authentication system:"
echo "   curl -X POST http://localhost:8000/api/auth/request-otp \\"
echo "        -H 'Content-Type: application/json' \\"
echo "        -d '{\"email\": \"test@example.com\"}'"
echo ""
echo "🔧 Useful commands:"
echo "   docker-compose logs -f app        # View app logs"
echo "   docker-compose logs -f queue      # View queue worker logs"
echo "   docker-compose exec app bash      # Access app container"
echo "   docker-compose down               # Stop all containers"
echo "   docker-compose up -d              # Start all containers"
echo "   docker-compose restart queue      # Restart queue workers"
echo ""
