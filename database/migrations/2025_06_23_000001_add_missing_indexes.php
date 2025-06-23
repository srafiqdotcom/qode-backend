<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // **qode** Add missing performance indexes for better query optimization
        
        // Cache table - index on expiration for cleanup queries
        Schema::table('cache', function (Blueprint $table) {
            $table->index(['expiration'], 'cache_expiration_index');
        });

        // Cache locks table - index on expiration for cleanup
        Schema::table('cache_locks', function (Blueprint $table) {
            $table->index(['expiration'], 'cache_locks_expiration_index');
        });

        // Blogs table - additional performance indexes
        Schema::table('blogs', function (Blueprint $table) {
            $table->index(['views_count'], 'blogs_views_count_index');
            $table->index(['comments_count'], 'blogs_comments_count_index');
            $table->index(['status', 'created_at'], 'blogs_status_created_at_index');
        });

        // Comments table - additional performance indexes
        Schema::table('comments', function (Blueprint $table) {
            $table->index(['approved_at'], 'comments_approved_at_index');
            $table->index(['blog_id', 'created_at'], 'comments_blog_created_index');
        });

        // OTPs table - additional performance indexes
        Schema::table('otps', function (Blueprint $table) {
            $table->index(['user_id', 'purpose', 'is_used'], 'otps_user_purpose_used_index');
            $table->index(['created_at'], 'otps_created_at_index');
        });

        // OAuth access tokens - performance indexes
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->index(['revoked'], 'oauth_access_tokens_revoked_index');
            $table->index(['expires_at'], 'oauth_access_tokens_expires_at_index');
            $table->index(['client_id'], 'oauth_access_tokens_client_id_index');
        });

        // OAuth auth codes - performance indexes  
        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->index(['revoked'], 'oauth_auth_codes_revoked_index');
            $table->index(['expires_at'], 'oauth_auth_codes_expires_at_index');
            $table->index(['client_id'], 'oauth_auth_codes_client_id_index');
        });

        // OAuth refresh tokens - performance indexes
        Schema::table('oauth_refresh_tokens', function (Blueprint $table) {
            $table->index(['revoked'], 'oauth_refresh_tokens_revoked_index');
            $table->index(['expires_at'], 'oauth_refresh_tokens_expires_at_index');
        });

        // Jobs table - queue processing optimization
        Schema::table('jobs', function (Blueprint $table) {
            $table->index(['available_at'], 'jobs_available_at_index');
            $table->index(['created_at'], 'jobs_created_at_index');
            $table->index(['queue', 'available_at'], 'jobs_queue_available_index');
        });

        // Failed jobs table - monitoring and cleanup
        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->index(['failed_at'], 'failed_jobs_failed_at_index');
        });

        // Sessions table - session management optimization
        Schema::table('sessions', function (Blueprint $table) {
            $table->index(['user_id', 'last_activity'], 'sessions_user_activity_index');
        });

        // Password reset tokens - cleanup optimization
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->index(['created_at'], 'password_reset_tokens_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // **qode** Remove performance indexes in reverse order
        
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropIndex('password_reset_tokens_created_at_index');
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex('sessions_user_activity_index');
        });

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropIndex('failed_jobs_failed_at_index');
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->dropIndex('jobs_available_at_index');
            $table->dropIndex('jobs_created_at_index');
            $table->dropIndex('jobs_queue_available_index');
        });

        Schema::table('oauth_refresh_tokens', function (Blueprint $table) {
            $table->dropIndex('oauth_refresh_tokens_revoked_index');
            $table->dropIndex('oauth_refresh_tokens_expires_at_index');
        });

        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->dropIndex('oauth_auth_codes_revoked_index');
            $table->dropIndex('oauth_auth_codes_expires_at_index');
            $table->dropIndex('oauth_auth_codes_client_id_index');
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->dropIndex('oauth_access_tokens_revoked_index');
            $table->dropIndex('oauth_access_tokens_expires_at_index');
            $table->dropIndex('oauth_access_tokens_client_id_index');
        });

        Schema::table('otps', function (Blueprint $table) {
            $table->dropIndex('otps_user_purpose_used_index');
            $table->dropIndex('otps_created_at_index');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_approved_at_index');
            $table->dropIndex('comments_blog_created_index');
        });

        Schema::table('blogs', function (Blueprint $table) {
            $table->dropIndex('blogs_views_count_index');
            $table->dropIndex('blogs_comments_count_index');
            $table->dropIndex('blogs_status_created_at_index');
        });

        Schema::table('cache_locks', function (Blueprint $table) {
            $table->dropIndex('cache_locks_expiration_index');
        });

        Schema::table('cache', function (Blueprint $table) {
            $table->dropIndex('cache_expiration_index');
        });
    }
};
