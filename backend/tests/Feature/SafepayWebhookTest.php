<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\PaymentSubmission;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafepayWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_verifies_and_deduplicates_safepay_events(): void
    {
        config(['services.safepay.webhook_secret' => 'test-webhook-secret']);
        $json = json_encode([
            'token' => 'evt_test_1',
            'version' => '2.0.0',
            'type' => 'payment.succeeded',
            'data' => ['tracker' => 'track_test_1', 'amount' => 500000, 'currency' => 'PKR'],
        ], JSON_THROW_ON_ERROR);
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SFPY_SIGNATURE' => hash_hmac('sha512', $json, 'test-webhook-secret'),
        ];

        $this->call('POST', '/api/webhooks/safepay', [], [], [], $headers, $json)
            ->assertOk()
            ->assertJson(['received' => true, 'duplicate' => false]);
        $this->call('POST', '/api/webhooks/safepay', [], [], [], $headers, $json)
            ->assertOk()
            ->assertJson(['received' => true, 'duplicate' => true]);
        $this->assertDatabaseCount('safepay_webhook_events', 1);
    }

    public function test_it_rejects_an_invalid_signature(): void
    {
        config(['services.safepay.webhook_secret' => 'test-webhook-secret']);
        $this->withHeader('X-SFPY-SIGNATURE', 'invalid')
            ->postJson('/api/webhooks/safepay', ['token' => 'evt_test_2', 'type' => 'payment.failed', 'data' => []])
            ->assertUnauthorized();
    }

    public function test_successful_payment_activates_subscription_once(): void
    {
        config(['services.safepay.webhook_secret' => 'test-webhook-secret']);
        $plan = Plan::create(['name' => 'Business', 'monthly_price' => 5000]);
        $business = Business::create(['name' => 'Store', 'slug' => 'store', 'plan_id' => null]);
        $this->useTenant($business->id);
        PaymentSubmission::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'method' => 'card',
            'amount' => 5000,
            'transaction_reference' => 'track_subscription_1',
            'safepay_tracker' => 'track_subscription_1',
            'status' => 'processing',
        ]);
        $json = json_encode([
            'token' => 'evt_subscription_1',
            'version' => '2.0.0',
            'type' => 'payment.succeeded',
            'data' => [
                'tracker' => ['token' => 'track_subscription_1'],
                'action' => ['payment_method' => ['last_four' => '1096']],
            ],
        ], JSON_THROW_ON_ERROR);
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SFPY_SIGNATURE' => hash_hmac('sha512', $json, 'test-webhook-secret'),
        ];

        $this->call('POST', '/api/webhooks/safepay', [], [], [], $headers, $json)->assertOk();
        $this->call('POST', '/api/webhooks/safepay', [], [], [], $headers, $json)->assertOk();

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseHas('payment_submissions', [
            'safepay_tracker' => 'track_subscription_1',
            'status' => 'approved',
            'card_last_four' => '1096',
        ]);
    }
}
