<?php

namespace Database\Factories;

use App\Models\Blog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BlogFactory extends Factory
{
    protected $model = Blog::class;

    public function definition(): array
    {
        $title = $this->generateRealisticTitle();
        $slug = Str::slug($title) . '-' . microtime(true) . '-' . uniqid();
        $content = $this->generateRealisticContent();
        $wordCount = str_word_count(strip_tags($content));
        $readingTime = max(1, ceil($wordCount / 200)); // 200 words per minute
        $publishedAt = fake()->boolean(75) ? fake()->dateTimeBetween('-2 years', 'now') : null;

        return [
            'uuid' => fake()->uuid(),
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $this->generateExcerpt($content),
            'description' => $content,
            'keywords' => $this->generateKeywords(),
            'status' => $publishedAt ? 'published' : fake()->randomElement(['draft', 'scheduled']),
            'image_path' => fake()->boolean(60) ? 'https://picsum.photos/800/400?random=' . rand(1, 1000) : null,
            'image_alt' => fake()->boolean(60) ? fake()->sentence(3) : null,
            'meta_title' => fake()->boolean(40) ? fake()->sentence(6) : null,
            'meta_description' => fake()->boolean(40) ? fake()->paragraph(2) : null,
            'published_at' => $publishedAt,
            'scheduled_at' => !$publishedAt && fake()->boolean(30) ? fake()->dateTimeBetween('now', '+1 month') : null,
            'author_id' => User::factory()->author(),
            'views_count' => $publishedAt ? fake()->numberBetween(0, 10000) : 0,
            'comments_count' => 0,
        ];
    }

    /**
     * Generate realistic blog titles
     */
    private function generateRealisticTitle(): string
    {
        $titleTemplates = [
            'Getting Started with {topic}',
            'Advanced {topic} Techniques',
            'Best Practices for {topic}',
            'Complete Guide to {topic}',
            'Mastering {topic} in {year}',
            '{topic} Tips and Tricks',
            'Building {project} with {topic}',
            'Common {topic} Mistakes to Avoid',
            'Performance Optimization in {topic}',
            'Security Best Practices for {topic}',
        ];

        $topics = [
            'Laravel', 'PHP', 'JavaScript', 'Vue.js', 'React', 'Docker', 'MySQL',
            'Redis', 'API Development', 'Web Development', 'DevOps', 'Testing'
        ];

        $projects = [
            'REST APIs', 'Web Applications', 'Microservices', 'E-commerce Sites',
            'Blog Systems', 'Dashboard Applications', 'Mobile Apps'
        ];

        $template = fake()->randomElement($titleTemplates);
        $topic = fake()->randomElement($topics);
        $project = fake()->randomElement($projects);
        $year = date('Y');

        return str_replace(['{topic}', '{project}', '{year}'], [$topic, $project, $year], $template);
    }

    /**
     * Generate realistic blog content
     */
    private function generateRealisticContent(): string
    {
        $topics = [
            'Laravel', 'PHP', 'JavaScript', 'Vue.js', 'React', 'Docker', 'MySQL',
            'Redis', 'API Development', 'Web Development', 'Programming', 'DevOps'
        ];

        $topic = fake()->randomElement($topics);

        $content = "# Introduction\n\n";
        $content .= fake()->paragraph(rand(3, 5)) . "\n\n";

        // Add sections
        for ($i = 0; $i < rand(3, 6); $i++) {
            $content .= "## " . fake()->sentence(rand(2, 4)) . "\n\n";
            $content .= fake()->paragraph(rand(4, 8)) . "\n\n";

            // Sometimes add code blocks
            if (fake()->boolean(30)) {
                $content .= "```php\n";
                $content .= $this->generateCodeSnippet();
                $content .= "\n```\n\n";
            }

            // Sometimes add lists
            if (fake()->boolean(40)) {
                $content .= "Key points:\n\n";
                for ($j = 0; $j < rand(3, 5); $j++) {
                    $content .= "- " . fake()->sentence() . "\n";
                }
                $content .= "\n";
            }
        }

        $content .= "## Conclusion\n\n";
        $content .= fake()->paragraph(rand(2, 4));

        return $content;
    }

    /**
     * Generate code snippets
     */
    private function generateCodeSnippet(): string
    {
        $snippets = [
            "<?php\n\nclass BlogController extends Controller\n{\n    public function index()\n    {\n        return Blog::published()->paginate(15);\n    }\n}",
            "Route::get('/api/blogs', [BlogController::class, 'index']);\nRoute::post('/api/blogs', [BlogController::class, 'store']);",
            "const fetchBlogs = async () => {\n    const response = await fetch('/api/blogs');\n    const data = await response.json();\n    return data;\n};",
            "SELECT * FROM blogs \nWHERE status = 'published' \nORDER BY created_at DESC;",
            "docker-compose up -d\ndocker-compose exec app php artisan migrate"
        ];

        return fake()->randomElement($snippets);
    }

    /**
     * Generate excerpt from content
     */
    private function generateExcerpt(string $content): string
    {
        $plainText = strip_tags($content);
        $sentences = explode('.', $plainText);
        $excerpt = implode('.', array_slice($sentences, 0, rand(2, 4))) . '.';
        return trim($excerpt);
    }

    /**
     * Generate realistic keywords
     */
    private function generateKeywords(): array
    {
        $allKeywords = [
            'laravel', 'php', 'javascript', 'vue', 'react', 'docker', 'mysql', 'redis',
            'api', 'rest', 'web-development', 'programming', 'backend', 'frontend',
            'database', 'performance', 'security', 'testing', 'devops', 'tutorial'
        ];

        return fake()->randomElements($allKeywords, rand(2, 6));
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'scheduled_at' => null,
            'views_count' => fake()->numberBetween(10, 5000),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
            'scheduled_at' => null,
            'views_count' => 0,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'published_at' => null,
            'scheduled_at' => fake()->dateTimeBetween('now', '+1 month'),
            'views_count' => 0,
        ]);
    }

    public function withImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'image_path' => 'blogs/' . fake()->uuid() . '.jpg',
            'image_alt' => fake()->sentence(3),
        ]);
    }

    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'views_count' => fake()->numberBetween(1000, 50000),
            'comments_count' => fake()->numberBetween(5, 100),
        ]);
    }
}
