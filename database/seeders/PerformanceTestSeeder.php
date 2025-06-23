<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Blog;
use App\Models\Comment;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PerformanceTestSeeder extends Seeder
{
    /**
     * Create realistic data distributions for performance testing
     */
    public function run(): void
    {
        $this->command->info('🎯 Creating realistic data distributions for performance testing...');
        
        // Create power users (users with lots of content)
        $this->createPowerUsers();
        
        // Create viral blogs (blogs with lots of views and comments)
        $this->createViralBlogs();
        
        // Create comment threads (deep nested conversations)
        $this->createCommentThreads();
        
        // Create tag distributions (some tags used much more than others)
        $this->createTagDistributions();
        
        // Create time-based patterns (more recent content)
        $this->createTimeBasedPatterns();
        
        // Update all counters and caches
        $this->updateCountersAndCaches();
        
        $this->command->info('✅ Performance test data created successfully!');
    }

    /**
     * Create power users with lots of content
     */
    private function createPowerUsers(): void
    {
        $this->command->info('👑 Creating power users...');
        
        // Get top 10% of authors
        $topAuthors = User::where('role', 'author')
            ->inRandomOrder()
            ->limit(100)
            ->get();
        
        foreach ($topAuthors as $author) {
            // Each power user gets 50-200 additional blogs
            $blogCount = fake()->numberBetween(50, 200);
            
            Blog::factory()
                ->published()
                ->popular()
                ->count($blogCount)
                ->create(['author_id' => $author->id]);
        }
        
        $this->command->info('✅ Power users created');
    }

    /**
     * Create viral blogs with high engagement
     */
    private function createViralBlogs(): void
    {
        $this->command->info('🔥 Creating viral blogs...');
        
        // Get random published blogs and make them viral
        $blogs = Blog::where('status', 'published')
            ->inRandomOrder()
            ->limit(500)
            ->get();
        
        foreach ($blogs as $blog) {
            // Update with viral metrics
            $blog->update([
                'views_count' => fake()->numberBetween(10000, 100000),
                'comments_count' => fake()->numberBetween(100, 1000),
            ]);
            
            // Create lots of comments for viral blogs
            $commentCount = fake()->numberBetween(50, 200);
            $userIds = User::pluck('id')->toArray();
            
            Comment::factory()
                ->approved()
                ->count($commentCount)
                ->create([
                    'blog_id' => $blog->id,
                    'user_id' => fake()->randomElement($userIds),
                ]);
        }
        
        $this->command->info('✅ Viral blogs created');
    }

    /**
     * Create deep comment threads
     */
    private function createCommentThreads(): void
    {
        $this->command->info('🧵 Creating comment threads...');
        
        // Get some popular blogs
        $popularBlogs = Blog::where('views_count', '>', 5000)
            ->limit(100)
            ->get();
        
        $userIds = User::pluck('id')->toArray();
        
        foreach ($popularBlogs as $blog) {
            // Create 3-5 thread starters
            $threadCount = fake()->numberBetween(3, 5);
            
            for ($i = 0; $i < $threadCount; $i++) {
                // Create parent comment
                $parentComment = Comment::factory()
                    ->approved()
                    ->create([
                        'blog_id' => $blog->id,
                        'user_id' => fake()->randomElement($userIds),
                    ]);
                
                // Create 2-4 levels of replies
                $this->createNestedReplies($parentComment, $userIds, 1, 4);
            }
        }
        
        $this->command->info('✅ Comment threads created');
    }

    /**
     * Create nested replies recursively
     */
    private function createNestedReplies(Comment $parentComment, array $userIds, int $currentLevel, int $maxLevel): void
    {
        if ($currentLevel >= $maxLevel) {
            return;
        }
        
        $replyCount = fake()->numberBetween(1, 3);
        
        for ($i = 0; $i < $replyCount; $i++) {
            $reply = Comment::factory()
                ->approved()
                ->create([
                    'blog_id' => $parentComment->blog_id,
                    'user_id' => fake()->randomElement($userIds),
                    'parent_id' => $parentComment->id,
                ]);
            
            // 50% chance to continue the thread
            if (fake()->boolean(50)) {
                $this->createNestedReplies($reply, $userIds, $currentLevel + 1, $maxLevel);
            }
        }
    }

    /**
     * Create realistic tag distributions (Pareto principle)
     */
    private function createTagDistributions(): void
    {
        $this->command->info('🏷️  Creating tag distributions...');
        
        $tags = Tag::all();
        $blogs = Blog::all();
        
        // Clear existing tag associations
        DB::table('blog_tags')->delete();
        
        // 20% of tags get 80% of usage (Pareto principle)
        $popularTags = $tags->take((int)($tags->count() * 0.2));
        $regularTags = $tags->skip((int)($tags->count() * 0.2));
        
        foreach ($blogs as $blog) {
            $tagCount = fake()->numberBetween(1, 5);
            $selectedTags = collect();
            
            // 80% chance to use popular tags
            if (fake()->boolean(80)) {
                $selectedTags = $selectedTags->merge(
                    $popularTags->random(min($tagCount, $popularTags->count()))
                );
            }
            
            // Fill remaining slots with regular tags
            $remainingSlots = $tagCount - $selectedTags->count();
            if ($remainingSlots > 0) {
                $selectedTags = $selectedTags->merge(
                    $regularTags->random(min($remainingSlots, $regularTags->count()))
                );
            }
            
            // Attach tags to blog
            $blog->tags()->attach($selectedTags->pluck('id')->toArray());
        }
        
        $this->command->info('✅ Tag distributions created');
    }

    /**
     * Create time-based patterns (more recent content)
     */
    private function createTimeBasedPatterns(): void
    {
        $this->command->info('📅 Creating time-based patterns...');
        
        // Update 30% of blogs to be very recent (last 30 days)
        $recentBlogCount = (int)(Blog::count() * 0.3);
        
        Blog::inRandomOrder()
            ->limit($recentBlogCount)
            ->get()
            ->each(function ($blog) {
                $blog->update([
                    'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
                    'published_at' => fake()->dateTimeBetween('-30 days', 'now'),
                    'updated_at' => fake()->dateTimeBetween('-15 days', 'now'),
                ]);
            });
        
        // Update 50% of comments to be recent
        $recentCommentCount = (int)(Comment::count() * 0.5);
        
        Comment::inRandomOrder()
            ->limit($recentCommentCount)
            ->get()
            ->each(function ($comment) {
                $comment->update([
                    'created_at' => fake()->dateTimeBetween('-60 days', 'now'),
                    'updated_at' => fake()->dateTimeBetween('-30 days', 'now'),
                ]);
            });
        
        $this->command->info('✅ Time-based patterns created');
    }

    /**
     * Update all counters and caches
     */
    private function updateCountersAndCaches(): void
    {
        $this->command->info('🔢 Updating counters and caches...');
        
        // Update blog comments count
        DB::statement('
            UPDATE blogs 
            SET comments_count = (
                SELECT COUNT(*) 
                FROM comments 
                WHERE comments.blog_id = blogs.id 
                AND comments.status = "approved"
            )
        ');
        
        // Update tag blogs count
        DB::statement('
            UPDATE tags 
            SET blogs_count = (
                SELECT COUNT(*) 
                FROM blog_tags 
                WHERE blog_tags.tag_id = tags.id
            )
        ');
        
        // Clear all caches to ensure fresh data
        Cache::flush();
        
        $this->command->info('✅ Counters and caches updated');
    }
}
