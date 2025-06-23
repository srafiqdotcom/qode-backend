<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //Commands\Command1::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('otp:cleanup')->daily();

        $schedule->command('blogs:publish-scheduled')->everyMinute();

        $schedule->command('queue:stats')->hourly()->appendOutputTo(storage_path('logs/queue-stats.log'));

        if (config('uploads.s3_migration.enabled')) {
            $schedule->command('images:migration-status')->dailyAt('09:00');
        }

        if (config('blog_cache.enabled')) {
            $schedule->command('search:stats')->dailyAt('02:00')->appendOutputTo(storage_path('logs/search-stats.log'));
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
