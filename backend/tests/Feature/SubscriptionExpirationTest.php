<?php

namespace Tests\Feature;

use App\Mail\SubscriptionExpiryReminderMail;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscriptionExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminders_are_queued_once_and_subscription_expires_on_end_date(): void
    {
        Mail::fake();
        $this->travelTo('2026-08-05 09:00:00');
        $plan = Plan::create(['name' => 'Basic', 'monthly_price' => 5000]);
        $business = Business::create([
            'name' => 'Dated Store', 'slug' => 'dated-store', 'plan_id' => $plan->id,
            'status' => 'active', 'active' => true,
        ]);
        User::factory()->create([
            'business_id' => $business->id, 'role' => 'owner', 'email' => 'owner@dated.test',
        ]);
        User::factory()->create([
            'business_id' => null, 'role' => 'super_admin', 'email' => 'admin@loyalty.test',
        ]);
        $this->useTenant($business->id);
        $subscription = Subscription::create([
            'business_id' => $business->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'amount_paid' => 5000, 'status' => 'active', 'starts_at' => now(),
            'ends_at' => now()->addDays(10),
        ]);

        $this->artisan('subscriptions:process-expirations')->assertSuccessful();
        $this->artisan('subscriptions:process-expirations')->assertSuccessful();
        Mail::assertQueued(SubscriptionExpiryReminderMail::class, 2);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'subscription.expiry_reminder_10_days', 'auditable_id' => (string) $subscription->id,
        ]);

        $this->travelTo('2026-08-15 09:01:00');
        $this->artisan('subscriptions:process-expirations')->assertSuccessful();
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'status' => 'expired']);
        $this->assertDatabaseHas('businesses', ['id' => $business->id, 'status' => 'expired', 'active' => false]);
    }
}
