<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement($this->getAllTagNames());

        return [
            'uuid' => fake()->uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->generateTagDescription($name),
            'color' => $this->generateTagColor($name),
            'blogs_count' => 0,
        ];
    }

    /**
     * Get all available tag names
     */
    private function getAllTagNames(): array
    {
        $techTags = [
            'Laravel', 'PHP', 'JavaScript', 'TypeScript', 'Vue.js', 'React', 'Angular',
            'Node.js', 'Express', 'Docker', 'Kubernetes', 'MySQL', 'PostgreSQL',
            'Redis', 'MongoDB', 'Elasticsearch', 'AWS', 'Azure', 'GCP',
            'API Development', 'REST API', 'GraphQL', 'Microservices', 'DevOps',
            'CI/CD', 'Testing', 'Unit Testing', 'Integration Testing', 'TDD',
            'Web Development', 'Frontend', 'Backend', 'Full Stack', 'Mobile Development',
            'Performance', 'Security', 'Authentication', 'Authorization', 'OAuth',
            'Database Design', 'SQL', 'NoSQL', 'Caching', 'Load Balancing',
            'Monitoring', 'Logging', 'Debugging', 'Code Review', 'Git',
            'Agile', 'Scrum', 'Project Management', 'Architecture', 'Design Patterns',
            'Clean Code', 'SOLID Principles', 'Refactoring', 'Legacy Code',
            'Machine Learning', 'AI', 'Data Science', 'Big Data', 'Analytics'
        ];

        $generalTags = [
            'Tutorial', 'Guide', 'Tips', 'Best Practices', 'How To', 'Getting Started',
            'Advanced', 'Beginner', 'Intermediate', 'Expert', 'Case Study',
            'Review', 'Comparison', 'Tools', 'Resources', 'News', 'Updates'
        ];

        // Create variations to have more unique names
        $variations = [];
        foreach (array_merge($techTags, $generalTags) as $tag) {
            $variations[] = $tag;
            $variations[] = $tag . ' 2024';
            $variations[] = $tag . ' Pro';
            $variations[] = $tag . ' Advanced';
            $variations[] = $tag . ' Basic';
            $variations[] = 'Modern ' . $tag;
            $variations[] = 'Latest ' . $tag;
        }

        return $variations;
    }



    /**
     * Generate tag description
     */
    private function generateTagDescription(string $name): string
    {
        $templates = [
            'Everything related to {name} development and best practices.',
            'Articles, tutorials, and guides about {name}.',
            'Latest news, tips, and tricks for {name}.',
            'In-depth coverage of {name} concepts and implementations.',
            'Practical examples and use cases for {name}.',
        ];

        $template = fake()->randomElement($templates);
        return str_replace('{name}', $name, $template);
    }

    /**
     * Generate appropriate colors for tags
     */
    private function generateTagColor(string $name): string
    {
        $colorMap = [
            'Laravel' => '#FF2D20',
            'PHP' => '#777BB4',
            'JavaScript' => '#F7DF1E',
            'TypeScript' => '#3178C6',
            'Vue.js' => '#4FC08D',
            'React' => '#61DAFB',
            'Angular' => '#DD0031',
            'Node.js' => '#339933',
            'Docker' => '#2496ED',
            'MySQL' => '#4479A1',
            'Redis' => '#DC382D',
            'AWS' => '#FF9900',
            'Security' => '#FF6B6B',
            'Performance' => '#4ECDC4',
            'Testing' => '#45B7D1',
        ];

        return $colorMap[$name] ?? fake()->hexColor();
    }



    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'blogs_count' => fake()->numberBetween(10, 100),
        ]);
    }

    public function withColor(string $color): static
    {
        return $this->state(fn (array $attributes) => [
            'color' => $color,
        ]);
    }
}
