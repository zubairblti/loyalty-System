<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaidOrder;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Services\LoyaltyService;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_scope_isolates_reads_and_rejects_cross_tenant_writes(): void
    {
        $plan = Plan::create(['name' => 'Test']);
        $first = Business::create(['plan_id' => $plan->id, 'name' => 'First', 'slug' => 'first']);
        $second = Business::create(['plan_id' => $plan->id, 'name' => 'Second', 'slug' => 'second']);

        $this->useTenant($first->id);
        $firstCustomer = Customer::create(['phone' => '+923001111111']);
        $this->useTenant($second->id);
        $secondCustomer = Customer::create(['phone' => '+923002222222']);

        $this->useTenant($first->id);
        $this->assertTrue(Customer::find($firstCustomer->id)->is($firstCustomer));
        $this->assertNull(Customer::find($secondCustomer->id));
        $this->assertSame(1, Customer::count());

        $this->expectException(LogicException::class);
        Customer::create(['business_id' => $second->id, 'phone' => '+923003333333']);
    }

    public function test_system_access_can_read_all_tenants(): void
    {
        $plan = Plan::create(['name' => 'Test']);
        $first = Business::create(['plan_id' => $plan->id, 'name' => 'First', 'slug' => 'first']);
        $second = Business::create(['plan_id' => $plan->id, 'name' => 'Second', 'slug' => 'second']);
        $this->useTenant($first->id);
        Customer::create(['phone' => '+923001111111']);
        $this->useTenant($second->id);
        Customer::create(['phone' => '+923002222222']);

        $this->useSystemAccess();
        $this->assertSame(2, Customer::count());
    }

    public function test_queue_job_cannot_process_an_order_from_another_tenant(): void
    {
        $plan = Plan::create(['name' => 'Test']);
        $first = Business::create(['plan_id' => $plan->id, 'name' => 'First', 'slug' => 'first']);
        $second = Business::create(['plan_id' => $plan->id, 'name' => 'Second', 'slug' => 'second']);
        $this->useTenant($second->id);
        $customer = Customer::create(['phone' => '+923002222222']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'source' => 'test',
            'external_id' => 'SECOND-1',
            'total' => 100,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->expectException(ModelNotFoundException::class);
        (new ProcessPaidOrder($order->id, $first->id))->handle(
            app(LoyaltyService::class),
            app(TenantContext::class),
        );
    }
}
