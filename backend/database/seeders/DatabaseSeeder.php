<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Plan;
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

        $plan = Plan::firstOrCreate(['name' => 'Pro'], [
            'domain_limit' => 3, 'qr_limit' => 25, 'terminal_limit' => 5, 'monthly_order_limit' => 10000,
        ]);
        $business = Business::firstOrCreate(['slug' => 'demo-cafe'], [
            'plan_id' => $plan->id, 'name' => 'Demo Cafe', 'points_per_100' => 5,
        ]);
        User::firstOrCreate(['email' => 'owner@example.com'], [
            'business_id' => $business->id, 'name' => 'Demo Owner', 'password' => 'password', 'role' => 'owner',
        ]);
    }
}
