<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\LoyaltyPointRule;
use App\Models\LoyaltySetting;
use App\Models\MembershipLevel;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PointsLedger;
use App\Models\Subscription;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_business_has_disabled_unconfigured_loyalty(): void
    {
        [$owner] = $this->businessOwner();
        $this->actingAs($owner)->getJson('/api/business/loyalty')->assertOk()
            ->assertJsonPath('settings.loyalty_enabled', false)
            ->assertJsonCount(0, 'rules')->assertJsonCount(0, 'levels');
    }

    public function test_tier_thresholds_are_unique_and_ascending_and_changes_are_audited(): void
    {
        [$owner, $business] = $this->businessOwner();
        $this->actingAs($owner)->putJson('/api/business/loyalty/settings', [
            'loyalty_enabled' => true, 'points_enabled' => true, 'memberships_enabled' => true,
        ])->assertOk();
        $silver = $this->actingAs($owner)->postJson('/api/business/loyalty/levels', $this->level('Silver', 500, 1))->assertCreated()->json('id');
        $this->actingAs($owner)->postJson('/api/business/loyalty/levels', $this->level('Gold', 1000, 2))->assertCreated();
        $this->actingAs($owner)->postJson('/api/business/loyalty/levels', $this->level('Invalid', 400, 3))->assertUnprocessable();
        $this->actingAs($owner)->postJson('/api/business/loyalty/levels', $this->level('Duplicate', 500, 4))->assertUnprocessable();
        $this->actingAs($owner)->deleteJson("/api/business/loyalty/levels/{$silver}")->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['business_id' => $business->id, 'action' => 'loyalty.enabled']);
        $this->assertDatabaseHas('audit_logs', ['business_id' => $business->id, 'action' => 'loyalty.tier_created']);
        $this->assertDatabaseHas('audit_logs', ['business_id' => $business->id, 'action' => 'loyalty.tier_deactivated']);
        $this->assertDatabaseHas('membership_levels', ['id' => $silver, 'active' => false]);
    }

    public function test_existing_tier_is_preserved_during_grace_and_downgrades_after_expiry(): void
    {
        [, $business] = $this->businessOwner();
        LoyaltySetting::create([
            'business_id' => $business->id,
            'loyalty_enabled' => true,
            'points_enabled' => true,
            'memberships_enabled' => true,
            'membership_downgrade_grace_days' => 30,
        ]);
        MembershipLevel::create([...$this->level('Silver', 500, 1), 'business_id' => $business->id]);
        $gold = MembershipLevel::create([...$this->level('Gold', 1000, 2), 'business_id' => $business->id]);
        $customer = Customer::create(['business_id' => $business->id, 'phone' => '+923001234567']);
        $loyalty = app(LoyaltyService::class);

        $this->assertSame('Gold', $loyalty->membership($customer, 1200)['current']->name);
        $gold->update(['active' => false]);
        $grace = $loyalty->membership($customer, 800);
        $this->assertSame('Gold', $grace['current']->name);
        $this->assertTrue($grace['is_grace_period']);

        $this->travel(31)->days();
        $downgraded = $loyalty->membership($customer, 800);
        $this->assertSame('Silver', $downgraded['current']->name);
        $this->assertDatabaseCount('customer_memberships', 2);
    }

    public function test_recovering_points_during_grace_keeps_the_existing_tier(): void
    {
        [, $business] = $this->businessOwner();
        LoyaltySetting::create(['business_id' => $business->id, 'loyalty_enabled' => true, 'points_enabled' => true, 'memberships_enabled' => true, 'membership_downgrade_grace_days' => 15]);
        MembershipLevel::create([...$this->level('Silver', 500, 1), 'business_id' => $business->id]);
        MembershipLevel::create([...$this->level('Gold', 1000, 2), 'business_id' => $business->id]);
        $customer = Customer::create(['business_id' => $business->id, 'phone' => '+923001234567']);
        $loyalty = app(LoyaltyService::class);

        $loyalty->membership($customer, 1200);
        $loyalty->membership($customer, 800);
        $recovered = $loyalty->membership($customer, 1000);

        $this->assertSame('Gold', $recovered['current']->name);
        $this->assertFalse($recovered['is_grace_period']);
        $this->assertDatabaseCount('customer_memberships', 1);
    }

    public function test_rule_changes_only_affect_future_orders(): void
    {
        [, $business] = $this->businessOwner();
        LoyaltySetting::create(['business_id' => $business->id, 'loyalty_enabled' => true, 'points_enabled' => true]);
        $rule = LoyaltyPointRule::create(['business_id' => $business->id, 'purchase_amount' => 100, 'earned_points' => 1]);
        $customer = Customer::create(['business_id' => $business->id, 'phone' => '+923001234567']);
        $first = Order::create(['business_id' => $business->id, 'customer_id' => $customer->id, 'source' => 'test', 'external_id' => 'A', 'total' => 500, 'status' => 'paid']);
        app(LoyaltyService::class)->earn($first);
        $rule->update(['earned_points' => 2]);
        $second = Order::create(['business_id' => $business->id, 'customer_id' => $customer->id, 'source' => 'test', 'external_id' => 'B', 'total' => 500, 'status' => 'paid']);
        app(LoyaltyService::class)->earn($second);

        $this->assertSame([5, 10], PointsLedger::orderBy('id')->pluck('points')->all());
    }

    private function businessOwner(): array
    {
        $plan = Plan::create(['name' => 'Loyalty Plan', 'monthly_price' => 5000]);
        $business = Business::create(['name' => 'Loyal Store', 'slug' => 'loyal-store', 'plan_id' => $plan->id, 'status' => 'active', 'active' => true, 'profile_completed_at' => now()]);
        $owner = User::factory()->create(['business_id' => $business->id, 'role' => 'owner']);
        $this->useTenant($business->id);
        Subscription::create(['business_id' => $business->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'amount_paid' => 5000, 'starts_at' => now(), 'ends_at' => now()->addMonth(), 'status' => 'active']);

        return [$owner, $business];
    }

    private function level(string $name, int $points, int $order): array
    {
        return ['name' => $name, 'required_points' => $points, 'display_order' => $order, 'badge_color' => '#e4b94e', 'icon' => 'badge', 'benefits' => ['Priority support'], 'active' => true];
    }
}
