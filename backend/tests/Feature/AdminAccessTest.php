<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_platform_dashboard(): void
    {
        $admin = User::factory()->create(['business_id' => null, 'role' => 'super_admin']);

        $this->actingAs($admin)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.businesses', 0);
    }

    public function test_business_owner_cannot_access_platform_dashboard(): void
    {
        $plan = Plan::create(['name' => 'Test']);
        $business = Business::create(['plan_id' => $plan->id, 'name' => 'Cafe', 'slug' => 'cafe']);
        $owner = User::factory()->create(['business_id' => $business->id, 'role' => 'owner']);

        $this->actingAs($owner)
            ->getJson('/api/admin/dashboard')
            ->assertForbidden();
    }
}
