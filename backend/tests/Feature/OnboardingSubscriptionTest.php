<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\PaymentSubmission;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OnboardingSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_business_is_blocked_until_payment_is_approved(): void
    {
        Mail::fake();
        $plan = Plan::create([
            'name' => 'Business',
            'monthly_price' => 5000,
            'features' => ['Points', 'POS'],
        ]);

        $this->postJson('/api/register', [
            'business_name' => 'New Store',
            'name' => 'Owner',
            'email' => 'owner@newstore.test',
            'phone' => '03001234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk()->assertJson(['sent' => true, 'expires_in' => 120]);

        $this->postJson('/api/register/verify', [
            'email' => 'owner@newstore.test',
            'code' => '123456',
        ])->assertSuccessful()->assertJsonPath('business.slug', 'new-store');

        $owner = User::where('email', 'owner@newstore.test')->firstOrFail();
        $this->assertNotNull($owner->email_verified_at);
        $this->getJson('/api/dashboard')->assertStatus(402);

        $this->postJson('/api/subscription/payments', [
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'method' => 'card',
            'transaction_reference' => 'PAY-1',
            'card_last_four' => '4242',
        ])->assertCreated();

        $admin = User::factory()->create(['business_id' => null, 'role' => 'super_admin']);
        $this->useSystemAccess();
        $payment = PaymentSubmission::firstOrFail();
        $this->actingAs($admin)->postJson("/api/admin/payments/{$payment->id}/review", [
            'status' => 'approved',
        ])->assertOk();

        $this->actingAs($owner)->getJson('/api/dashboard')->assertOk();
        $this->assertSame($plan->id, Business::where('slug', 'new-store')->value('plan_id'));
    }
}
