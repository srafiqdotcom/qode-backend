<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run()
    {
        User::create([
            'name' => 'Admin Author',
            'email' => 'admin@example.com',
            'role' => 'author',
        ]);

        User::create([
            'name' => 'Test Reader',
            'email' => 'reader@example.com',
            'role' => 'reader',
        ]);

        User::factory()->author()->count(10)->create();
        User::factory()->reader()->count(20)->create();
    }
}
