<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\PaymentSubmission;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafepayCheckoutSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_session_is_hidden_until_card_is_submitted(): void
    {
        $plan = Plan::create(['name' => 'Card Plan', 'monthly_price' => 5000]);
        $business = Business::create(['name' => 'Card Store', 'slug' => 'card-store', 'plan_id' => null]);
        $owner = User::factory()->create(['business_id' => $business->id, 'role' => 'owner']);
        $admin = User::factory()->create(['business_id' => null, 'role' => 'super_admin']);
        $this->useTenant($business->id);
        $payment = PaymentSubmission::create([
            'business_id' => $business->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'method' => 'card', 'amount' => 5000, 'transaction_reference' => 'track_session',
            'safepay_tracker' => 'track_session', 'status' => 'initiated',
        ]);

        $this->actingAs($admin)->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonCount(0, 'payments')
            ->assertJsonPath('payment_metrics.processing_total', 0);

        $this->actingAs($owner)->postJson('/api/subscription/safepay/track_session/processing')
            ->assertOk()->assertJsonPath('status', 'processing');
        $this->assertDatabaseHas('payment_submissions', ['id' => $payment->id, 'status' => 'processing']);
    }
}
