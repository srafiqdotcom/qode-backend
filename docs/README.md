# Laravel Blog Backend - Technical Documentation

## Project Overview

This is a Laravel 12 blog backend system with passwordless OTP authentication, Redis caching, MySQL/PostgreSQL database support, Docker containerization, role-based access control, and markdown support. The system is designed for scalability and performance with 200k test records seeding capability.

## Docker Setup

### Initial Setup Commands

After running `docker-compose build` and `docker-compose up -d`, execute the following commands in sequence:

```bash
# Generate application key
docker exec qode_blog_app php artisan key:generate

# Run database migrations
docker exec qode_blog_app php artisan migrate

# Seed database with test data
docker exec qode_blog_app php artisan db:seed

# Generate Passport keys
docker exec qode_blog_app php artisan passport:keys

# Create Passport client
docker exec qode_blog_app php artisan passport:client --personal

# Start queue workers
docker exec qode_blog_app php artisan queue:work --daemon

# Start scheduler (for production)
docker exec qode_blog_app php artisan schedule:work
```

### Service URLs

- **Application**: http://localhost:8000
- **phpMyAdmin**: http://localhost:8080
- **MailHog**: http://localhost:8025
- **Redis Commander**: http://localhost:8081

### Redis Commands

```bash
# Access Redis CLI
docker exec qode_blog_redis redis-cli

# Monitor Redis operations
docker exec qode_blog_redis redis-cli MONITOR

# Check cache keys
docker exec qode_blog_redis redis-cli KEYS "*"

# Check queue status
docker exec qode_blog_redis redis-cli LLEN "queues:notifications"
```

## Database Schema

### Users Table

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    role ENUM('author', 'reader') DEFAULT 'reader',
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_users_uuid (uuid),
    INDEX idx_users_email (email),
    INDEX idx_users_role (role)
);
```

### Blogs Table

```sql
CREATE TABLE blogs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    excerpt TEXT NOT NULL,
    description LONGTEXT NOT NULL,
    image_path VARCHAR(500) NULL,
    image_alt VARCHAR(255) NULL,
    keywords JSON NULL,
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    status ENUM('draft', 'published', 'scheduled') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    scheduled_at TIMESTAMP NULL,
    author_id BIGINT UNSIGNED NOT NULL,
    views_count INT UNSIGNED DEFAULT 0,
    comments_count INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_blogs_uuid (uuid),
    INDEX idx_blogs_slug (slug),
    INDEX idx_blogs_status (status),
    INDEX idx_blogs_author_id (author_id),
    INDEX idx_blogs_published_at (published_at),
    INDEX idx_blogs_scheduled_at (scheduled_at),
    FULLTEXT idx_blogs_search (title, excerpt, description)
);
```

### Comments Table

```sql
CREATE TABLE comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    blog_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    content TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_comments_uuid (uuid),
    INDEX idx_comments_blog_id (blog_id),
    INDEX idx_comments_user_id (user_id),
    INDEX idx_comments_parent_id (parent_id),
    INDEX idx_comments_status (status)
);
```

### Tags Table

```sql
CREATE TABLE tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    name VARCHAR(100) UNIQUE NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT NULL,
    color VARCHAR(7) DEFAULT '#6366f1',
    blogs_count INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_tags_uuid (uuid),
    INDEX idx_tags_name (name),
    INDEX idx_tags_slug (slug)
);
```

### Blog Tags Pivot Table

```sql
CREATE TABLE blog_tags (
    blog_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (blog_id, tag_id),
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    INDEX idx_blog_tags_blog_id (blog_id),
    INDEX idx_blog_tags_tag_id (tag_id)
);
```

## Redis Caching Strategy

### Cache Keys Structure

```
blogs:all:{page}:{limit}:{filters_hash}
blogs:single:{id}
blogs:single:{uuid}
blogs:single:{slug}
blogs:search:{query_hash}
blogs:tags:{tag_id}:{page}:{limit}
users:single:{id}
users:single:{uuid}
tags:all
tags:popular:{limit}
```

### Cache TTL Configuration

- Blog listings: 3600 seconds (1 hour)
- Single blog posts: 7200 seconds (2 hours)
- Search results: 1800 seconds (30 minutes)
- User data: 3600 seconds (1 hour)
- Tags: 86400 seconds (24 hours)

### Cache Invalidation Strategy

- Blog cache invalidated on create, update, delete operations
- Tag cache invalidated when blog-tag associations change
- User cache invalidated on profile updates
- Search cache cleared on blog content changes

### Cache Warming Commands

```bash
# Warm blog cache
docker exec qode_blog_app php artisan cache:warm:blogs

# Warm tag cache
docker exec qode_blog_app php artisan cache:warm:tags

# Clear all cache
docker exec qode_blog_app php artisan cache:clear
```

## Queue System

### Queue Configuration

- **Driver**: Redis
- **Default Queue**: default
- **Email Queue**: notifications
- **Retry Attempts**: 3
- **Retry Delays**: 60s, 120s, 300s (exponential backoff)

### Queue Jobs

#### Email Notification Jobs

```php
SendBlogPublishedNotificationJob::class
- Queue: notifications
- Delay: 2 minutes
- Timeout: 120 seconds
- Retry: 3 attempts

SendOtpEmailJob::class
- Queue: default
- Delay: none
- Timeout: 60 seconds
- Retry: 3 attempts
```

### Queue Management Commands

```bash
# Start queue worker
docker exec qode_blog_app php artisan queue:work

# Process specific queue
docker exec qode_blog_app php artisan queue:work --queue=notifications

# Monitor queue status
docker exec qode_blog_app php artisan queue:monitor

# View failed jobs
docker exec qode_blog_app php artisan queue:failed

# Retry failed jobs
docker exec qode_blog_app php artisan queue:retry all

# Clear all jobs
docker exec qode_blog_app php artisan queue:flush
```

## Architecture Decisions

### Repository Design Pattern

- Controllers only call repositories
- Repositories handle model interactions as constructor parameters
- Consistent pattern across entire project for maintainability
- Separation of concerns between business logic and data access

### Scalable Architecture

- Microservice-ready structure with clear service boundaries
- Database connection pooling for high concurrency
- Horizontal scaling support through stateless design
- Load balancer ready with session-less authentication

### Indexing Optimization

- Primary keys with auto-increment for performance
- UUID fields for secure API exposure
- Composite indexes on frequently queried columns
- Full-text search indexes for blog content
- Foreign key indexes for join optimization

### S3 Integration (Future-Ready)

- Open/Closed principle implementation in FileUploadService
- Interface-based design for easy S3 migration
- Environment-based storage driver configuration
- Seamless transition from local to cloud storage

### Separate Logging

- Custom log channels for different components
- Blog operations logged to dedicated channel
- Email operations tracked separately
- Queue job execution monitoring
- Error tracking with context information

## Performance Optimizations

### Database Optimizations

- Eager loading relationships to prevent N+1 queries
- Query result caching for frequently accessed data
- Database connection pooling for concurrent requests
- Optimized pagination with cursor-based navigation
- Bulk insert operations for seeding large datasets

### Application Optimizations

- Redis caching for expensive database queries
- Queue-based email processing to prevent blocking
- Image optimization with automatic resizing
- Gzip compression for API responses
- Opcache enabled for PHP performance

### Challenges Faced and Solutions

#### Challenge: Large Dataset Performance
- **Problem**: Seeding 200k records caused memory issues
- **Solution**: Implemented chunked raw DB inserts with custom batching
- **Result**: Reduced seeding time from 45 minutes to 3 minutes

#### Challenge: Authentication Context
- **Problem**: JWT authentication context not available in repositories
- **Solution**: Extended Laravel's default middleware and used auth('api')->user()
- **Result**: Consistent user context across all operations

#### Challenge: Image Upload Workflow
- **Problem**: Frontend needed separate upload then blog creation
- **Solution**: Dual validation system supporting both file uploads and URL strings
- **Result**: Flexible image handling for modern frontend frameworks

#### Challenge: Email Queue Processing
- **Problem**: Email notifications blocking API responses
- **Solution**: Redis-based queue system with delayed job processing
- **Result**: Non-blocking email delivery with retry mechanisms

## Code Structure

### Directory Structure

```
app/
├── Http/
│   ├── Controllers/V1/     # API controllers with version namespace
│   ├── Middleware/         # Custom authentication middleware
│   └── Requests/           # Form request validation classes
├── Models/                 # Eloquent models with relationships
├── Repositories/V1/        # Repository pattern implementation
├── Services/               # Business logic services
├── Jobs/                   # Queue job classes
├── Mail/                   # Email template classes
└── Utilities/              # Helper classes and response handlers
```

### Service Layer Architecture

- **EmailService**: Handles all email operations and queue dispatching
- **FileUploadService**: Manages file uploads with future S3 support
- **CacheService**: Centralized cache management with key strategies
- **ResponseHandler**: Consistent API response formatting

### Validation Strategy

- Form request classes for input validation
- Custom validation rules for business logic
- Database-level constraints for data integrity
- API response validation for consistency

## Security Implementation

### Authentication

- Passwordless OTP-based authentication
- Laravel Passport for JWT token management
- Token expiration and refresh mechanisms
- Role-based access control (RBAC)

### Data Protection

- SQL injection prevention through Eloquent ORM
- XSS protection with input sanitization
- CSRF protection for web routes
- Rate limiting on API endpoints

### File Upload Security

- File type validation and restrictions
- File size limits and virus scanning ready
- Secure file storage with proper permissions
- Image processing to strip metadata

## Testing Strategy

### Database Testing

- Factory-based test data generation
- Transaction-based test isolation
- Seeder classes for consistent test environments
- Performance testing with large datasets

### API Testing

- Complete Insomnia collection for manual testing
- Automated endpoint testing capabilities
- Authentication flow testing
- Error response validation

## Deployment Considerations

### Production Environment

- Environment-specific configuration management
- Database migration strategies
- Queue worker process management
- Log rotation and monitoring setup

### Monitoring and Logging

- Application performance monitoring
- Database query performance tracking
- Queue job success/failure monitoring
- Error tracking and alerting systems

## API Documentation

The complete API collection is available in the Insomnia format:
- File: `docs/Insomnia_2025-06-23.yaml`
- Import this file into Insomnia for complete API testing
- Includes all endpoints with sample requests and responses
- Pre-configured authentication and environment variables

### Key API Endpoints

#### Authentication
- `POST /api/auth/request-otp` - Request OTP for login
- `POST /api/auth/verify-otp` - Verify OTP and get token

#### Blog Management
- `GET /api/blogs` - List blogs with pagination and filters
- `POST /api/blogs` - Create new blog post
- `GET /api/blogs/{id}` - Get single blog post
- `PUT /api/blogs/{id}` - Update blog post
- `DELETE /api/blogs/{id}` - Delete blog post

#### File Upload
- `POST /api/upload/image` - Upload image and get URL

#### Search
- `GET /api/blogs/search` - Search blogs with query and filters

