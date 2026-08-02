<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(['email' => 'admin@example.com'], [
            'business_id' => null,
            'name' => 'Platform Admin',
            'password' => 'password',
            'role' => 'super_admin',
            'email_verified_at' => now(),
        ]);
    }
}
