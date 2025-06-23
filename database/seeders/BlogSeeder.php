<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Blog;
use App\Models\Tag;
use App\Models\User;
use App\Models\Comment;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class BlogSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Preparing seed...');

        $authors = User::where('role', 'author')->get();
        $readers = User::where('role', 'reader')->get();

        if ($authors->isEmpty()) {
            $authors = User::factory()->author()->count(5)->create();
        }

        if ($readers->isEmpty()) {
            $readers = User::factory()->reader()->count(10)->create();
        }

        $tags = Tag::factory()->count(20)->create();

        // BLOG SEEDING
        $this->seedBlogsInChunks('published', $authors, 150000);
        $this->seedBlogsInChunks('draft', $authors, 30000);
        $this->seedBlogsInChunks('scheduled', $authors, 20000);

        // TAG ATTACHMENT
        $this->command->info("Attaching tags to blogs...");
        Blog::chunk(1000, function ($blogs) use ($tags) {
            foreach ($blogs as $blog) {
                $randomTags = $tags->random(rand(1, 5))->pluck('id');
                $blog->tags()->attach($randomTags);
            }
        });

        // COMMENTS
        $this->command->info("Creating comments on first 50k published blogs...");
        Blog::where('status', 'published')->take(50000)->chunk(1000, function ($blogs) use ($readers) {
            foreach ($blogs as $blog) {
                $commentCount = rand(0, 15);
                if ($commentCount > 0) {
                    Comment::factory()->count($commentCount)->approved()->create([
                        'blog_id' => $blog->id,
                        'user_id' => fn() => $readers->random()->id,
                    ]);
                }
            }
        });

        $this->command->info('✅ Seeder complete: 200k blogs + tags + comments');
    }

    private function seedBlogsInChunks(string $status, $authors, int $count)
    {
        $this->command->info("Seeding $status blogs: $count rows");

        $chunks = (int) ceil($count / 1000);
        for ($i = 0; $i < $chunks; $i++) {
            $this->command->info("Chunk $i / $chunks");
            $blogs = [];

            for ($j = 0; $j < 1000; $j++) {
                $author = $authors->random();
                $title = fake()->sentence(rand(4, 8));
                $excerpt = fake()->paragraph(rand(1, 2));
                $description = fake()->paragraphs(rand(3, 8), true);
                $keywords = fake()->randomElements([
                    'laravel', 'php', 'javascript', 'vue', 'react', 'docker', 'mysql',
                    'redis', 'api', 'backend', 'frontend', 'web-development', 'tutorial',
                    'guide', 'tips', 'best-practices', 'performance', 'security', 'testing'
                ], rand(2, 6));

                $blogs[] = [
                    'uuid' => fake()->uuid(),
                    'author_id' => $author->id,
                    'title' => $title,
                    'slug' => Str::slug($title) . '-' . uniqid() . '-' . $i . '-' . $j,
                    'excerpt' => $excerpt,
                    'description' => $description,
                    'keywords' => json_encode($keywords),
                    'image_path' => fake()->boolean(70) ? 'https://picsum.photos/800/400?random=' . fake()->numberBetween(1, 10000) : null,
                    'image_alt' => fake()->boolean(60) ? fake()->sentence(3) : null,
                    'meta_title' => fake()->boolean(40) ? fake()->sentence(6) : null,
                    'meta_description' => fake()->boolean(40) ? fake()->text(160) : null,
                    'status' => $status,
                    'published_at' => $status === 'published' ? fake()->dateTimeBetween('-2 years', 'now') : null,
                    'scheduled_at' => $status === 'scheduled' ? fake()->dateTimeBetween('now', '+3 months') : null,
                    'views_count' => fake()->numberBetween(0, 50000),
                    'comments_count' => 0,
                    'created_at' => fake()->dateTimeBetween('-2 years', 'now'),
                    'updated_at' => fake()->dateTimeBetween('-1 year', 'now'),
                ];
            }

            DB::table('blogs')->insert($blogs);
        }
    }
}
