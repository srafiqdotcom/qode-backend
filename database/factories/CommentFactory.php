<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Blog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        $status = fake()->randomElement(['approved', 'pending', 'rejected']);
        $spamScore = $this->calculateSpamScore($status);

        return [
            'uuid' => fake()->uuid(),
            'blog_id' => Blog::factory(),
            'user_id' => User::factory(),
            'content' => $this->generateRealisticComment(),
            'status' => $status,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'approved_at' => $status === 'approved' ? fake()->dateTimeBetween('-1 month', 'now') : null,
            'approved_by' => $status === 'approved' ? User::factory()->author() : null,
        ];
    }

    /**
     * Generate realistic comment content
     */
    private function generateRealisticComment(): string
    {
        $commentTypes = [
            'positive' => [
                'Great article! This really helped me understand {topic}.',
                'Thanks for sharing this. Very informative and well-written.',
                'Excellent explanation! I\'ve been struggling with this concept.',
                'This is exactly what I was looking for. Bookmarked for future reference.',
                'Amazing tutorial! Step-by-step instructions are very clear.',
            ],
            'question' => [
                'How would you handle {scenario} in this case?',
                'What about performance implications when dealing with large datasets?',
                'Is there a way to optimize this further?',
                'Have you considered using {alternative} instead?',
                'Could you provide more details about the {aspect} part?',
            ],
            'experience' => [
                'I\'ve been using this approach for months and it works great.',
                'In my experience, {alternative} might be better for production.',
                'We implemented something similar at our company with good results.',
                'I had the same issue and this solution worked perfectly.',
                'Been following your blog for a while, always quality content!',
            ],
            'technical' => [
                'The code example in line {number} could be improved by {suggestion}.',
                'You might want to add error handling for edge cases.',
                'Consider using dependency injection for better testability.',
                'What about backward compatibility with older versions?',
                'Security-wise, make sure to validate all inputs properly.',
            ]
        ];

        $type = fake()->randomElement(array_keys($commentTypes));
        $template = fake()->randomElement($commentTypes[$type]);

        // Replace placeholders
        $replacements = [
            '{topic}' => fake()->randomElement(['Laravel', 'PHP', 'JavaScript', 'APIs', 'databases']),
            '{scenario}' => fake()->randomElement(['error handling', 'validation', 'caching', 'scaling']),
            '{alternative}' => fake()->randomElement(['Redis', 'Elasticsearch', 'GraphQL', 'microservices']),
            '{aspect}' => fake()->randomElement(['implementation', 'configuration', 'deployment', 'testing']),
            '{number}' => fake()->numberBetween(1, 50),
            '{suggestion}' => fake()->randomElement(['adding validation', 'using constants', 'extracting methods']),
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Sometimes add additional sentences
        if (fake()->boolean(30)) {
            $content .= ' ' . fake()->sentence();
        }

        return $content;
    }

    /**
     * Calculate spam score based on status
     */
    private function calculateSpamScore(string $status): float
    {
        return match($status) {
            'approved' => fake()->randomFloat(2, 0, 0.3),
            'pending' => fake()->randomFloat(2, 0.2, 0.7),
            'rejected' => fake()->randomFloat(2, 0.6, 1.0),
            default => 0.0
        };
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'approved_by' => User::factory()->author(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }

    public function reply(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => Comment::factory(),
        ]);
    }
}
