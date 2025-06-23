<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Blog;
use App\Models\Comment;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LargeDatasetSeeder extends Seeder
{
    /**
     * Seed 200k blog records efficiently for performance testing
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting large dataset seeding (200k blog records)...');
        $startTime = microtime(true);

        // Disable foreign key checks for faster insertion
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            // Step 1: Create Users (5k users - enough authors for 200k blogs)
            $this->seedUsers();

            // Step 2: Create Tags (1000 tags for better variety)
            $this->seedTags();

            // Step 3: Create Blogs (200k blogs in small chunks)
            $this->seedBlogs();

            // Step 4: Create Comments (100k comments)
            $this->seedComments();

            // Step 5: Attach tags to blogs (in chunks)
            $this->attachTagsToBlogs();

            // Step 6: Update counters
            $this->updateCounters();

        } finally {
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        $this->command->info("✅ Large dataset seeding completed in {$duration} seconds!");
        $this->logSeedingStats();
    }

    /**
     * Seed 5,000 users efficiently (enough authors for 200k blogs)
     */
    private function seedUsers(): void
    {
        $this->command->info('👥 Seeding 5,000 users...');

        // Create admin users (20)
        User::factory()->admin()->count(20)->create();

        // Create author users (2,000 - each can write 100 blogs on average)
        User::factory()->author()->count(2000)->create();

        // Create reader users (2,980)
        User::factory()->reader()->count(2980)->create();

        $this->command->info('✅ Users seeded successfully');
    }

    /**
     * Seed 1000 realistic tags for better variety
     */
    private function seedTags(): void
    {
        $this->command->info('🏷️  Seeding 1,000 tags...');

        // Create tags (1000)
        Tag::factory()->count(1000)->create();

        $this->command->info('✅ Tags seeded successfully');
    }

    /**
     * Seed 200,000 blogs efficiently using small chunks (200 loops of 1000 records)
     */
    private function seedBlogs(): void
    {
        $this->command->info('📝 Seeding 200,000 blogs in small chunks...');

        $userIds = User::where('role', 'author')->pluck('id')->toArray();
        $chunkSize = 1000;  // Small chunks to prevent memory issues
        $totalBlogs = 200000;
        $totalLoops = 200;  // 200 loops of 1000 records each

        for ($loop = 0; $loop < $totalLoops; $loop++) {
            $loopNumber = $loop + 1;
            $this->command->info("   Processing chunk {$loopNumber}/{$totalLoops}...");

            // Create blogs with realistic distribution
            $publishedCount = 750;  // 75% published
            $draftCount = 200;      // 20% draft
            $scheduledCount = 50;   // 5% scheduled

            // Create published blogs
            Blog::factory()
                ->published()
                ->count($publishedCount)
                ->create(['author_id' => fake()->randomElement($userIds)]);

            // Create draft blogs
            Blog::factory()
                ->draft()
                ->count($draftCount)
                ->create(['author_id' => fake()->randomElement($userIds)]);

            // Create scheduled blogs
            Blog::factory()
                ->scheduled()
                ->count($scheduledCount)
                ->create(['author_id' => fake()->randomElement($userIds)]);

            // Progress tracking
            $completed = $loopNumber * $chunkSize;
            $progress = round(($completed / $totalBlogs) * 100, 1);
            $this->command->info("   Progress: {$progress}% ({$completed}/{$totalBlogs})");

            // Force garbage collection to free memory
            if ($loopNumber % 10 === 0) {
                gc_collect_cycles();
                $this->command->info("   Memory cleanup completed");
            }
        }

        $this->command->info('✅ 200,000 blogs seeded successfully');
    }

    /**
     * Seed 100,000 comments efficiently in small chunks
     */
    private function seedComments(): void
    {
        $this->command->info('💬 Seeding 100,000 comments in small chunks...');

        $publishedBlogIds = Blog::where('status', 'published')->pluck('id')->toArray();
        $userIds = User::pluck('id')->toArray();
        $chunkSize = 1000;
        $totalComments = 100000;
        $totalLoops = 100; // 100 loops of 1000 comments each

        for ($loop = 0; $loop < $totalLoops; $loop++) {
            $loopNumber = $loop + 1;
            $this->command->info("   Processing comment chunk {$loopNumber}/{$totalLoops}...");

            // Create comments with realistic status distribution
            $approvedCount = 800;  // 80% approved
            $pendingCount = 150;   // 15% pending
            $rejectedCount = 50;   // 5% rejected

            // Create approved comments
            Comment::factory()
                ->approved()
                ->count($approvedCount)
                ->create([
                    'blog_id' => fake()->randomElement($publishedBlogIds),
                    'user_id' => fake()->randomElement($userIds),
                ]);

            // Create pending comments
            Comment::factory()
                ->pending()
                ->count($pendingCount)
                ->create([
                    'blog_id' => fake()->randomElement($publishedBlogIds),
                    'user_id' => fake()->randomElement($userIds),
                ]);

            // Create rejected comments
            Comment::factory()
                ->rejected()
                ->count($rejectedCount)
                ->create([
                    'blog_id' => fake()->randomElement($publishedBlogIds),
                    'user_id' => fake()->randomElement($userIds),
                ]);

            // Progress tracking
            $completed = $loopNumber * $chunkSize;
            $progress = round(($completed / $totalComments) * 100, 1);
            $this->command->info("   Progress: {$progress}% ({$completed}/{$totalComments})");

            // Force garbage collection every 20 chunks
            if ($loopNumber % 20 === 0) {
                gc_collect_cycles();
                $this->command->info("   Memory cleanup completed");
            }
        }

        // Create some nested comments (replies)
        $this->createNestedComments();

        $this->command->info('✅ 100,000 comments seeded successfully');
    }

    /**
     * Create nested comments (replies)
     */
    private function createNestedComments(): void
    {
        $this->command->info('🔗 Creating nested comments...');
        
        $parentComments = Comment::where('status', 'approved')
            ->whereNull('parent_id')
            ->inRandomOrder()
            ->limit(5000)
            ->get();
        
        $userIds = User::pluck('id')->toArray();
        
        foreach ($parentComments as $parentComment) {
            // 30% chance of having replies
            if (fake()->boolean(30)) {
                $replyCount = fake()->numberBetween(1, 3);
                
                Comment::factory()
                    ->approved()
                    ->count($replyCount)
                    ->create([
                        'blog_id' => $parentComment->blog_id,
                        'user_id' => fake()->randomElement($userIds),
                        'parent_id' => $parentComment->id,
                    ]);
            }
        }
        
        $this->command->info('✅ Nested comments created');
    }

    /**
     * Attach tags to blogs with realistic distribution in small chunks
     */
    private function attachTagsToBlogs(): void
    {
        $this->command->info('🔗 Attaching tags to blogs in small chunks...');

        $blogIds = Blog::pluck('id')->toArray();
        $tagIds = Tag::pluck('id')->toArray();
        $chunkSize = 2000; // Smaller chunks to prevent memory issues
        $totalBlogs = count($blogIds);
        $chunks = array_chunk($blogIds, $chunkSize);
        $totalChunks = count($chunks);

        foreach ($chunks as $index => $chunk) {
            $chunkNumber = $index + 1;
            $this->command->info("   Processing tag attachment chunk {$chunkNumber}/{$totalChunks}...");

            $attachments = [];

            foreach ($chunk as $blogId) {
                // Each blog gets 2-5 random tags
                $blogTagIds = fake()->randomElements($tagIds, fake()->numberBetween(2, 5));

                foreach ($blogTagIds as $tagId) {
                    $attachments[] = [
                        'blog_id' => $blogId,
                        'tag_id' => $tagId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Bulk insert for performance
            if (!empty($attachments)) {
                // Insert in smaller batches to avoid query size limits
                $insertChunks = array_chunk($attachments, 1000);
                foreach ($insertChunks as $insertChunk) {
                    DB::table('blog_tags')->insert($insertChunk);
                }
            }

            // Progress tracking
            $progress = round(($chunkNumber / $totalChunks) * 100, 1);
            $this->command->info("   Progress: {$progress}% ({$chunkNumber}/{$totalChunks})");

            // Force garbage collection every 10 chunks
            if ($chunkNumber % 10 === 0) {
                gc_collect_cycles();
                $this->command->info("   Memory cleanup completed");
            }
        }

        $this->command->info('✅ Tags attached to blogs successfully');
    }

    /**
     * Update counter caches for performance
     */
    private function updateCounters(): void
    {
        $this->command->info('🔢 Updating counter caches...');
        
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
        
        $this->command->info('✅ Counter caches updated');
    }

    /**
     * Log seeding statistics
     */
    private function logSeedingStats(): void
    {
        $userCount = User::count();
        $blogCount = Blog::count();
        $commentCount = Comment::count();
        $tagCount = Tag::count();
        $blogTagCount = DB::table('blog_tags')->count();

        $stats = [
            'users' => $userCount,
            'blogs' => $blogCount,
            'comments' => $commentCount,
            'tags' => $tagCount,
            'blog_tags' => $blogTagCount,
        ];

        $this->command->info('📊 Final Statistics:');
        foreach ($stats as $model => $count) {
            $this->command->info("   {$model}: " . number_format($count));
        }

        Log::info('Large dataset seeding completed', $stats);
    }
}
