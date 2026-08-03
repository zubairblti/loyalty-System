<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PointsLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_login_and_view_rewards_dashboard(): void
    {
        Mail::fake();
        $this->app->bind(SmsSender::class, fn () => new class implements SmsSender
        {
            public function send(string $recipient, string $message): void
            {
                throw new \RuntimeException('SMS provider unavailable.');
            }
        });
        $plan = Plan::create(['name' => 'Test']);
        $business = Business::create(['plan_id' => $plan->id, 'name' => 'Cafe', 'slug' => 'cafe']);

        $this->postJson('/api/customer/cafe/register', [
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'phone' => '03001234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk()->assertJsonMissingPath('demo_code');

        $this->postJson('/api/customer/cafe/register/verify', [
            'email' => 'customer@example.com',
            'code' => '123456',
        ])->assertOk()->assertJsonPath('balance', 0);

        $this->useTenant($business->id);
        $customer = Customer::where('phone', '+923001234567')->firstOrFail();
        $order = Order::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'source' => 'mini_pos',
            'external_id' => 'POS-100',
            'total' => 1000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        PointsLedger::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'points' => 50,
            'type' => 'earn',
            'idempotency_key' => 'test-earn',
        ]);

        $this->getJson('/api/customer/cafe/dashboard')
            ->assertOk()
            ->assertJsonPath('balance', 50)
            ->assertJsonCount(1, 'transactions')
            ->assertJsonCount(1, 'orders');

        $this->postJson('/api/customer/cafe/logout')->assertNoContent();
        $this->postJson('/api/customer/cafe/login', ['phone' => $customer->phone, 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('customer.email', 'customer@example.com');

        $this->postJson('/api/customer/cafe/profile/phone/otp', ['phone' => '03011112222'])
            ->assertOk()
            ->assertJson(['sent' => true, 'expires_in' => 120]);
        $this->postJson('/api/customer/cafe/profile/phone/verify', ['phone' => '03011112222', 'code' => '123456'])
            ->assertOk()
            ->assertJsonPath('phone', '+923011112222');
    }

    public function test_customer_dashboard_requires_customer_session(): void
    {
        $plan = Plan::create(['name' => 'Test']);
        Business::create(['plan_id' => $plan->id, 'name' => 'Cafe', 'slug' => 'cafe']);
        $this->getJson('/api/customer/cafe/dashboard')->assertUnauthorized();
    }
}
