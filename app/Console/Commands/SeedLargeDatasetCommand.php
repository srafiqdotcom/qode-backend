<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Database\Seeders\LargeDatasetSeeder;

class SeedLargeDatasetCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:seed-large 
                            {--force : Force seeding without confirmation}
                            {--chunk-size=1000 : Number of records to process at once}
                            {--memory-limit=2G : Memory limit for the process}';

    /**
     * The console command description.
     */
    protected $description = 'Seed database with 200k blog records plus supporting data for performance testing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Set memory limit
        $memoryLimit = $this->option('memory-limit');
        ini_set('memory_limit', $memoryLimit);
        
        $this->info("🚀 Large Dataset Seeder");
        $this->info("Memory limit set to: {$memoryLimit}");
        $this->newLine();

        // Check current database state
        $this->displayCurrentStats();

        // Confirm before proceeding
        if (!$this->option('force')) {
            if (!$this->confirm('This will create 200k test records. Continue?')) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Warning about time and resources
        $this->warn('⚠️  This operation may take 20-45 minutes depending on your system.');
        $this->warn('⚠️  Ensure you have sufficient disk space (1GB+) and memory (2GB+).');
        $this->warn('⚠️  Will create 200k blogs + 100k comments + supporting data.');
        $this->newLine();

        // Start seeding
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
            // Run the seeder
            $seeder = new LargeDatasetSeeder();
            $seeder->setCommand($this);
            $seeder->run();

            // Display completion stats
            $this->displayCompletionStats($startTime, $startMemory);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Seeding failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            
            return Command::FAILURE;
        }
    }

    /**
     * Display current database statistics
     */
    private function displayCurrentStats(): void
    {
        $this->info('📊 Current Database Statistics:');
        
        $tables = ['users', 'blogs', 'comments', 'tags'];
        
        foreach ($tables as $table) {
            try {
                $count = DB::table($table)->count();
                $this->line("   {$table}: " . number_format($count));
            } catch (\Exception $e) {
                $this->line("   {$table}: Table not found");
            }
        }
        
        $this->newLine();
    }

    /**
     * Display completion statistics
     */
    private function displayCompletionStats(float $startTime, int $startMemory): void
    {
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $duration = round($endTime - $startTime, 2);
        $memoryUsed = $this->formatBytes($endMemory - $startMemory);
        $peakMemory = $this->formatBytes(memory_get_peak_usage(true));
        
        $this->newLine();
        $this->info('🎉 Seeding Completed Successfully!');
        $this->newLine();
        
        $this->info('⏱️  Performance Metrics:');
        $this->line("   Duration: {$duration} seconds");
        $this->line("   Memory used: {$memoryUsed}");
        $this->line("   Peak memory: {$peakMemory}");
        $this->newLine();
        
        // Display final counts
        $this->info('📊 Final Database Statistics:');
        
        $finalStats = [
            'users' => DB::table('users')->count(),
            'blogs' => DB::table('blogs')->count(),
            'comments' => DB::table('comments')->count(),
            'tags' => DB::table('tags')->count(),
            'blog_tags' => DB::table('blog_tags')->count(),
        ];
        
        $totalRecords = array_sum($finalStats);
        
        foreach ($finalStats as $table => $count) {
            $this->line("   {$table}: " . number_format($count));
        }
        
        $this->newLine();
        $this->info("📈 Total records created: " . number_format($totalRecords));
        
        // Calculate records per second
        $recordsPerSecond = round($totalRecords / $duration, 0);
        $this->info("⚡ Processing speed: " . number_format($recordsPerSecond) . " records/second");
        
        $this->newLine();
        $this->info('💡 Next steps:');
        $this->line('   - Test API performance with large dataset');
        $this->line('   - Run search indexing: php artisan search:index --rebuild');
        $this->line('   - Monitor database performance');
        $this->line('   - Test caching effectiveness');
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
