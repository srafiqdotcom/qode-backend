# Laravel Blog Backend - Docker Setup

## 🚀 Automated Docker Setup

This Laravel blog backend is fully containerized with automated setup for all services including Passport, queue workers, Redis, and email processing.

## 📋 Prerequisites

- Docker (20.10+)
- Docker Compose (2.0+)
- Git

## ⚡ Quick Start

### 1. Clone and Setup

```bash
git clone <repository-url>
cd blog-backend
chmod +x docker/scripts/setup.sh
./docker/scripts/setup.sh
```

### 2. That's it! 

The setup script automatically handles:
- ✅ Environment configuration
- ✅ Container building and startup
- ✅ Database migrations
- ✅ Laravel Passport installation and key generation
- ✅ Queue worker setup
- ✅ Redis configuration
- ✅ Email system setup with MailHog
- ✅ Proper permissions and caching

## 🏗️ Architecture

### Services

| Service | Container | Port | Purpose |
|---------|-----------|------|---------|
| **App** | `qode_blog_app` | 8000 | Laravel PHP-FPM application |
| **Database** | `qode_blog_mysql` | 3306 | MySQL 8.0 database |
| **Cache/Queue** | `qode_blog_redis` | 6379 | Redis for caching and queues |
| **Queue Worker** | `qode_blog_queue` | - | Processes email and background jobs |
| **Scheduler** | `qode_blog_scheduler` | - | Laravel cron job scheduler |
| **Web Server** | `qode_blog_nginx` | 8000 | Nginx reverse proxy |
| **Email Testing** | `qode_blog_mailhog` | 8025 | MailHog email capture |
| **DB Management** | `qode_blog_phpmyadmin` | 8080 | phpMyAdmin interface |

### Automated Initialization

Each container runs an initialization script that:

1. **Waits for dependencies** (database, Redis)
2. **Sets proper permissions** for Laravel storage
3. **Generates application keys** if needed
4. **Runs database migrations** automatically
5. **Installs Laravel Passport** with key generation
6. **Creates OAuth clients** for authentication
7. **Caches configurations** for performance
8. **Starts appropriate services** based on container role

## 🔧 Container Roles

The same Docker image is used for different roles:

- **`CONTAINER_ROLE=app`** - PHP-FPM application server
- **`CONTAINER_ROLE=queue`** - Queue worker for background jobs
- **`CONTAINER_ROLE=scheduler`** - Laravel task scheduler

## 📧 Email System

### Automatic Setup
- Queue workers automatically process email jobs
- MailHog captures all emails for testing
- Redis manages the email queue

### Testing Emails
1. **Send OTP Request**: 
   ```bash
   curl -X POST http://localhost:8000/api/auth/request-otp \
     -H "Content-Type: application/json" \
     -d '{"email": "test@example.com"}'
   ```

2. **Check MailHog**: Visit `http://localhost:8025` to see the email

3. **Verify OTP**:
   ```bash
   curl -X POST http://localhost:8000/api/auth/verify-otp \
     -H "Content-Type: application/json" \
     -d '{"email": "test@example.com", "otp_code": "123456", "purpose": "login"}'
   ```

## 🔐 Laravel Passport

### Automatic Configuration
- Encryption keys generated automatically
- Personal access client created
- Proper permissions set
- Ready for JWT token generation

### Manual Commands (if needed)
```bash
# Regenerate Passport keys
docker-compose exec app php artisan passport:keys --force

# Create new client
docker-compose exec app php artisan passport:client --personal
```

## 🔄 Queue Workers

### Automatic Setup
- Queue workers start automatically
- Process `emails` and `default` queues
- Retry failed jobs 3 times
- 60-second timeout per job

### Manual Queue Commands
```bash
# View queue status
docker-compose exec app php artisan queue:monitor

# Process queue once
docker-compose exec app php artisan queue:work --once

# Restart queue workers
docker-compose restart queue

# View failed jobs
docker-compose exec app php artisan queue:failed
```

## 🗄️ Database

### Automatic Setup
- MySQL 8.0 with health checks
- Database and user created automatically
- Migrations run on startup
- Ready for connections

### Manual Database Commands
```bash
# Run migrations
docker-compose exec app php artisan migrate

# Seed database
docker-compose exec app php artisan db:seed

# Access MySQL directly
docker-compose exec mysql mysql -u laravel -ppassword qode_blog
```

## 🔧 Useful Commands

### Container Management
```bash
# View all container status
docker-compose ps

# View logs
docker-compose logs -f app
docker-compose logs -f queue
docker-compose logs -f mysql

# Restart services
docker-compose restart app
docker-compose restart queue

# Access containers
docker-compose exec app bash
docker-compose exec mysql bash
```

### Laravel Commands
```bash
# Clear caches
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear

# View routes
docker-compose exec app php artisan route:list

# Tinker (Laravel REPL)
docker-compose exec app php artisan tinker
```

### Development
```bash
# Watch logs in real-time
docker-compose logs -f

# Rebuild containers
docker-compose build --no-cache
docker-compose up -d

# Reset everything
docker-compose down -v
docker-compose up -d
```

## 🌐 Access Points

- **Main Application**: http://localhost:8000
- **MailHog (Email Testing)**: http://localhost:8025
- **phpMyAdmin (Database)**: http://localhost:8080
- **API Documentation**: Available via Insomnia collection

## 🐛 Troubleshooting

### Common Issues

1. **Port Conflicts**: Change ports in `docker-compose.yml`
2. **Permission Issues**: Containers handle permissions automatically
3. **Database Connection**: Health checks ensure database is ready
4. **Queue Not Processing**: Check `docker-compose logs queue`
5. **Passport Errors**: Keys are generated automatically on startup

### Reset Everything
```bash
docker-compose down -v --remove-orphans
docker system prune -f
./docker/scripts/setup.sh
```

### Check Service Health
```bash
# Test API
curl http://localhost:8000/api/blogs

# Test database
docker-compose exec app php artisan migrate:status

# Test Redis
docker-compose exec app php artisan tinker --execute="Redis::ping()"

# Test queue
docker-compose exec app php artisan queue:size
```

## 🎯 Production Notes

For production deployment:
1. Change `APP_ENV=production` in environment
2. Set strong passwords and secrets
3. Use external database and Redis services
4. Configure proper SSL certificates
5. Set up monitoring and logging
6. Use container orchestration (Kubernetes, Docker Swarm)

## 📚 Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Laravel Passport](https://laravel.com/docs/passport)
- [Docker Compose](https://docs.docker.com/compose/)
- [Redis](https://redis.io/documentation)
- [MailHog](https://github.com/mailhog/MailHog)
