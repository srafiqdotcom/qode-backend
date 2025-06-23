#!/bin/bash

echo "🚀 Setting up Qode Blog Application..."
echo "======================================"

# Function to check if command was successful
check_status() {
    if [ $? -eq 0 ]; then
        echo "✅ $1"
    else
        echo "❌ $1 failed"
        exit 1
    fi
}

# Step 1: Copy environment file
echo "📋 Copying environment configuration..."
cp .env.docker .env
check_status "Environment file copied"

# Step 2: Stop any existing containers
echo "🛑 Stopping existing containers..."
docker-compose down -v 2>/dev/null || true
check_status "Existing containers stopped"

# Step 3: Build containers
echo "🔨 Building Docker containers..."
docker-compose build --no-cache
check_status "Docker containers built"

# Step 4: Start containers
echo "🚀 Starting containers..."
docker-compose up -d
check_status "Containers started"

# Step 5: Wait for MySQL to be ready
echo "⏳ Waiting for MySQL to initialize (30 seconds)..."
sleep 30

# Step 6: Install Composer dependencies
echo "📦 Installing Composer dependencies..."
docker-compose exec app composer install --optimize-autoloader --no-interaction
check_status "Composer dependencies installed"

# Step 7: Set proper permissions
echo "🔐 Setting proper file permissions..."
docker-compose exec app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
check_status "File ownership set"

docker-compose exec app chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
check_status "File permissions set"

# Step 8: Generate application key
echo "🔑 Generating application key..."
docker-compose exec app php artisan key:generate --force
check_status "Application key generated"

# Step 9: Run database migrations
echo "📊 Running database migrations..."
docker-compose exec app php artisan migrate --force
check_status "Database migrations completed"

# Step 10: Create storage link
echo "🔗 Creating storage symbolic link..."
docker-compose exec app php artisan storage:link
check_status "Storage link created"

# Step 11: Clear and cache configuration
echo "🧹 Optimizing application..."
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:cache
check_status "Application optimized"

# Step 12: Verify setup
echo "🔍 Verifying setup..."
docker-compose exec app php artisan --version > /dev/null
check_status "Laravel application verified"

echo ""
echo "🎉 Setup completed successfully!"
echo "================================"
echo ""
echo "🌐 Access your application:"
echo "   📱 Main App: http://localhost:8000"
echo "   📧 MailHog: http://localhost:8025"
echo "   🗄️  phpMyAdmin: http://localhost:8080"
echo ""
echo "📊 Database credentials:"
echo "   🏷️  Database: qode_blog"
echo "   👤 Username: laravel"
echo "   🔒 Password: password"
echo "   🔑 Root Password: rootpassword"
echo ""
echo "🔧 Useful commands:"
echo "   📋 View logs: docker-compose logs -f"
echo "   🛑 Stop: docker-compose down"
echo "   🔄 Restart: docker-compose restart"
echo "   🐚 Shell access: docker-compose exec app bash"
echo ""
echo "✨ Your Qode Blog is ready to use!"
