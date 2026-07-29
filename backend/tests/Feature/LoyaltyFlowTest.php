<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaidOrder;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PointsLedger;
use App\Services\LoyaltyService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_order_awards_points_only_once(): void
    {
        $plan = Plan::create(['name' => 'Test']);
        $business = Business::create(['plan_id' => $plan->id, 'name' => 'Cafe', 'slug' => 'cafe', 'points_per_100' => 5]);
        $customer = Customer::create(['business_id' => $business->id, 'phone' => '03001234567']);
        $order = Order::create([
            'business_id' => $business->id, 'customer_id' => $customer->id, 'source' => 'mini_pos',
            'external_id' => 'POS-1', 'total' => 1250, 'status' => 'paid', 'paid_at' => now(),
        ]);

        (new ProcessPaidOrder($order->id))->handle(app(LoyaltyService::class));
        (new ProcessPaidOrder($order->id))->handle(app(LoyaltyService::class));

        $this->assertSame(62, (int) PointsLedger::where('customer_id', $customer->id)->sum('points'));
        $this->assertDatabaseCount('points_ledger', 1);
    }

    public function test_duplicate_external_order_is_rejected_by_database(): void
    {
        $plan = Plan::create(['name' => 'Test']);
        $business = Business::create(['plan_id' => $plan->id, 'name' => 'Cafe', 'slug' => 'cafe']);
        $attributes = ['business_id' => $business->id, 'source' => 'custom', 'external_id' => 'ORDER-1', 'total' => 100, 'status' => 'paid'];
        Order::create($attributes);

        $this->expectException(QueryException::class);
        Order::create($attributes);
    }
}
