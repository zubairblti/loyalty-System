<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_can_customize_and_reset_its_branding(): void
    {
        Storage::fake('local');
        [$owner, $business] = $this->activeBusiness();

        $this->actingAs($owner)->getJson('/api/business/branding')
            ->assertOk()
            ->assertJsonPath('brand_name', 'Brand Store')
            ->assertJsonPath('brand_primary_color', '#1d252b')
            ->assertJsonPath('brand_accent_color', '#e4b94e');

        $response = $this->actingAs($owner)->post('/api/business/branding', [
            'brand_name' => 'Brand Store Rewards',
            'brand_primary_color' => '#123456',
            'brand_accent_color' => '#fedcba',
            'brand_text_color' => '#112233',
            'logo' => UploadedFile::fake()->image('logo.png', 240, 240),
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('brand_name', 'Brand Store Rewards');
        $business->refresh();
        Storage::disk('local')->assertExists($business->brand_logo_path);
        $this->assertDatabaseHas('audit_logs', ['business_id' => $business->id, 'action' => 'branding.updated']);
        $this->actingAs($owner)->get('/api/business/branding/logo')->assertOk();
        $this->get("/api/customer/{$business->slug}/logo")->assertOk();
        $this->getJson("/api/customer/{$business->slug}/business")
            ->assertJsonPath('brand_primary_color', '#123456')
            ->assertJsonPath('brand_text_color', '#112233')
            ->assertJsonPath('brand_name', 'Brand Store Rewards');

        $oldPath = $business->brand_logo_path;
        $this->actingAs($owner)->deleteJson('/api/business/branding')
            ->assertOk()
            ->assertJsonPath('brand_primary_color', '#1d252b')
            ->assertJsonPath('brand_text_color', '#ffffff')
            ->assertJsonPath('logo_url', null);
        Storage::disk('local')->assertMissing($oldPath);
        $this->assertDatabaseHas('audit_logs', ['business_id' => $business->id, 'action' => 'branding.reset']);
    }

    public function test_branding_rejects_invalid_colors(): void
    {
        [$owner] = $this->activeBusiness();

        $this->actingAs($owner)->postJson('/api/business/branding', [
            'brand_name' => 'Unsafe Brand',
            'brand_primary_color' => 'red',
            'brand_accent_color' => '#ffffff',
            'brand_text_color' => '#ffffff',
        ])->assertUnprocessable()->assertJsonValidationErrors('brand_primary_color');
    }

    private function activeBusiness(): array
    {
        $plan = Plan::create(['name' => 'Brand Plan', 'monthly_price' => 5000, 'active' => true, 'public' => true]);
        $business = Business::create([
            'name' => 'Brand Store', 'slug' => 'brand-store', 'plan_id' => $plan->id,
            'status' => 'active', 'active' => true, 'profile_completed_at' => now(),
        ]);
        $owner = User::factory()->create(['business_id' => $business->id, 'role' => 'owner']);
        $this->useTenant($business->id);
        Subscription::create([
            'business_id' => $business->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'amount_paid' => 5000, 'starts_at' => now(), 'ends_at' => now()->addMonth(), 'status' => 'active',
        ]);

        return [$owner, $business];
    }
}
