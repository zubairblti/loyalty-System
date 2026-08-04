<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\BusinessActivatedNotification;
use App\Services\SubscriptionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class AdminBusinessManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_business_with_registration_fields_and_activate_cash_plan(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['business_id' => null, 'role' => 'super_admin']);
        $this->actingAs($admin)->postJson('/api/admin/businesses', [
            'business_name' => 'Admin Store', 'name' => 'Store Owner',
            'email' => 'owner@admin-store.test', 'phone' => '03001234567',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertCreated()->assertJsonPath('status', 'pending');

        $business = Business::where('slug', 'admin-store')->firstOrFail();
        $owner = User::where('email', 'owner@admin-store.test')->firstOrFail();
        $this->assertNotNull($owner->email_verified_at);
        auth('web')->logout();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => $owner->email, 'password' => 'password123'])
            ->assertOk()->assertJsonPath('id', $owner->id);
        $plan = Plan::create([
            'name' => 'Growth', 'monthly_price' => 8000, 'yearly_discount_percent' => 30,
            'duration_months' => 1, 'features' => ['POS'], 'active' => true, 'public' => true,
        ]);
        $this->actingAs($admin)->postJson("/api/admin/businesses/{$business->id}/cash-payment", [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'payment_date' => now()->toDateString(), 'activation_reason' => 'Cash received at office.',
            'admin_note' => 'Verified against the cash collection report.',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertOk()->assertJsonPath('status', 'paid');

        $this->assertDatabaseHas('businesses', ['id' => $business->id, 'status' => 'active', 'active' => true]);
        $this->assertDatabaseHas('subscriptions', ['business_id' => $business->id, 'plan_id' => $plan->id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['business_id' => $business->id, 'action' => 'business.created_by_admin']);
        $paymentAudit = AuditLog::where('business_id', $business->id)->where('action', 'payment.approved')->firstOrFail();
        $this->assertSame('monthly', $paymentAudit->new_values['billing_cycle']);
        $this->assertSame('cash', $paymentAudit->new_values['payment_method']);
        $this->assertSame('Cash received at office.', $paymentAudit->new_values['activation_reason']);
        $this->assertSame('Verified against the cash collection report.', $paymentAudit->new_values['admin_note']);
        $this->assertNotEmpty($paymentAudit->new_values['subscription_start_date']);
        $this->assertNotEmpty($paymentAudit->new_values['subscription_end_date']);
        Notification::assertSentTo($owner, BusinessActivatedNotification::class);
    }

    public function test_cash_activation_is_atomic_and_duplicate_safe(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['business_id' => null, 'role' => 'super_admin']);
        $business = Business::create(['name' => 'Atomic Store', 'slug' => 'atomic-store', 'plan_id' => null, 'status' => 'pending', 'active' => false]);
        User::factory()->create(['business_id' => $business->id, 'role' => 'owner']);
        $plan = Plan::create(['name' => 'Atomic Plan', 'monthly_price' => 5000, 'active' => true, 'public' => true]);
        $payload = [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'amount' => 5000,
            'payment_date' => '2026-08-15', 'idempotency_key' => (string) Str::uuid(),
        ];

        $this->actingAs($admin)->postJson("/api/admin/businesses/{$business->id}/cash-payment", $payload)->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/businesses/{$business->id}/cash-payment", $payload)->assertOk();
        $this->assertDatabaseCount('payment_submissions', 1);
        $this->assertDatabaseCount('subscriptions', 1);

        $this->actingAs($admin)->postJson("/api/admin/businesses/{$business->id}/cash-payment", [
            ...$payload, 'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(409);

        $secondBusiness = Business::create(['name' => 'Rollback Store', 'slug' => 'rollback-store', 'plan_id' => null, 'status' => 'pending', 'active' => false]);
        $mock = Mockery::mock(SubscriptionManager::class);
        $mock->shouldReceive('activate')->once()->andThrow(new \RuntimeException('Forced activation failure'));
        $this->app->instance(SubscriptionManager::class, $mock);
        $this->actingAs($admin)->postJson("/api/admin/businesses/{$secondBusiness->id}/cash-payment", [
            ...$payload, 'idempotency_key' => (string) Str::uuid(), 'payment_date' => '2026-08-16',
        ])->assertStatus(500);
        $this->assertDatabaseMissing('payment_submissions', ['business_id' => $secondBusiness->id]);
        $this->assertDatabaseMissing('subscriptions', ['business_id' => $secondBusiness->id]);
    }

    public function test_admin_can_manage_multiple_plans(): void
    {
        $admin = User::factory()->create(['business_id' => null, 'role' => 'super_admin']);
        $payload = [
            'name' => 'Starter', 'description' => 'For small teams', 'monthly_price' => 3000,
            'yearly_discount_percent' => 30, 'duration_months' => 1, 'features' => ['Rewards'],
            'domain_limit' => 1, 'qr_limit' => 5, 'terminal_limit' => 1,
            'monthly_order_limit' => 1000, 'active' => true, 'public' => true, 'display_order' => 1,
        ];
        $id = $this->actingAs($admin)->postJson('/api/admin/plans', $payload)
            ->assertCreated()->json('id');
        $this->actingAs($admin)->putJson("/api/admin/plans/{$id}", [...$payload, 'name' => 'Starter Plus', 'public' => false])
            ->assertOk()->assertJsonPath('public', false);
        $this->actingAs($admin)->putJson("/api/admin/plans/{$id}", [...$payload, 'name' => 'Starter Plus', 'public' => false, 'active' => false])
            ->assertOk()->assertJsonPath('active', false);
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.activated', 'auditable_id' => (string) $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.deactivated', 'auditable_id' => (string) $id]);
        $this->actingAs($admin)->deleteJson("/api/admin/plans/{$id}")->assertNoContent();
        $this->assertSoftDeleted('plans', ['id' => $id]);
        $this->actingAs($admin)->postJson("/api/admin/plans/{$id}/restore")->assertOk();
        $this->assertDatabaseHas('plans', ['id' => $id, 'deleted_at' => null]);
    }

    public function test_admin_can_search_and_filter_business_directory(): void
    {
        $admin = User::factory()->create(['business_id' => null, 'role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Directory Plan', 'monthly_price' => 3000]);
        $matching = Business::create(['name' => 'Searchable Cafe', 'slug' => 'searchable-cafe', 'plan_id' => $plan->id, 'status' => 'active']);
        User::factory()->create(['business_id' => $matching->id, 'role' => 'owner', 'name' => 'Special Owner', 'email' => 'special@owner.test', 'phone' => '+923001112233']);
        Business::create(['name' => 'Hidden Store', 'slug' => 'hidden-store', 'plan_id' => null, 'status' => 'pending']);

        $this->actingAs($admin)->getJson('/api/admin/businesses?search=special&status=active&plan_id='.$plan->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $matching->id);
    }
}
