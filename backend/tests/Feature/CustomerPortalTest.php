<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PointsLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_verify_otp_and_view_rewards_dashboard(): void
    {
        $plan = Plan::create(['name' => 'Test']);
        $business = Business::create(['plan_id' => $plan->id, 'name' => 'Cafe', 'slug' => 'cafe']);
        $customer = Customer::create(['business_id' => $business->id, 'phone' => '03001234567', 'name' => 'Customer']);
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

        $this->postJson('/api/customer/cafe/otp', ['phone' => $customer->phone])
            ->assertOk()
            ->assertJson(['sent' => true, 'demo_code' => '123456']);

        $this->postJson('/api/customer/cafe/verify', ['phone' => $customer->phone, 'code' => '123456'])
            ->assertOk()
            ->assertJsonPath('balance', 50)
            ->assertJsonPath('tier', 'Member');

        $this->getJson('/api/customer/cafe/dashboard')
            ->assertOk()
            ->assertJsonCount(1, 'transactions')
            ->assertJsonCount(1, 'orders');
    }

    public function test_customer_dashboard_requires_customer_session(): void
    {
        $this->getJson('/api/customer/cafe/dashboard')->assertUnauthorized();
    }
}
