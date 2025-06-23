<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Default seeding for development
        $this->command->info('🌱 Seeding development dataset...');
        $this->call([
            UserSeeder::class,
            BlogSeeder::class,
        ]);

        $this->command->info('✅ Development seeding completed!');
        $this->command->info('💡 For performance testing with 200k records, use: php artisan db:seed-large');
    }
}
