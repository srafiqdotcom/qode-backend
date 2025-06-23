<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SearchService;
use App\Models\Blog;

class SearchIndexCommand extends Command
{
    protected $signature = 'search:index 
                            {--rebuild : Rebuild the entire search index}
                            {--clear : Clear the search index}
                            {--blog= : Index a specific blog by ID}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Manage the Redis search index for blogs';

    public function handle(SearchService $searchService)
    {
        $rebuild = $this->option('rebuild');
        $clear = $this->option('clear');
        $blogId = $this->option('blog');
        $force = $this->option('force');

        if ($clear) {
            return $this->clearIndex($searchService, $force);
        }

        if ($rebuild) {
            return $this->rebuildIndex($searchService, $force);
        }

        if ($blogId) {
            return $this->indexSingleBlog($searchService, $blogId);
        }

        $this->info('Please specify an action: --rebuild, --clear, or --blog=ID');
        return 1;
    }

    private function clearIndex(SearchService $searchService, bool $force): int
    {
        if (!$force && !$this->confirm('Are you sure you want to clear the entire search index?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $this->info('Clearing search index...');

        if ($searchService->clearSearchIndex()) {
            $this->info('Search index cleared successfully.');
            return 0;
        } else {
            $this->error('Failed to clear search index.');
            return 1;
        }
    }

    private function rebuildIndex(SearchService $searchService, bool $force): int
    {
        $blogCount = Blog::published()->count();

        if (!$force && !$this->confirm("Are you sure you want to rebuild the search index? This will process {$blogCount} blogs.")) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $this->info('Rebuilding search index...');
        $this->info("Processing {$blogCount} published blogs...");

        $progressBar = $this->output->createProgressBar($blogCount);
        $progressBar->start();

        $indexed = 0;
        $failed = 0;

        Blog::with(['author', 'tags'])
            ->published()
            ->chunk(100, function ($blogs) use ($searchService, $progressBar, &$indexed, &$failed) {
                foreach ($blogs as $blog) {
                    try {
                        if ($searchService->indexBlog($blog)) {
                            $indexed++;
                        } else {
                            $failed++;
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        $this->newLine();
                        $this->error("Failed to index blog {$blog->id}: " . $e->getMessage());
                    }
                    
                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Search index rebuild completed:");
        $this->line("- Successfully indexed: {$indexed} blogs");
        
        if ($failed > 0) {
            $this->error("- Failed to index: {$failed} blogs");
            return 1;
        }

        return 0;
    }

    private function indexSingleBlog(SearchService $searchService, string $blogId): int
    {
        $blog = Blog::with(['author', 'tags'])->find($blogId);

        if (!$blog) {
            $this->error("Blog with ID {$blogId} not found.");
            return 1;
        }

        $this->info("Indexing blog: {$blog->title}");

        if ($searchService->indexBlog($blog)) {
            $this->info('Blog indexed successfully.');
            return 0;
        } else {
            $this->error('Failed to index blog.');
            return 1;
        }
    }
}
