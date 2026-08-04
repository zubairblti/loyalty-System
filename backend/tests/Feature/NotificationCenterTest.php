<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_notifications_are_deduplicated_paginated_and_marked_read(): void
    {
        $business = Business::create(['plan_id' => Plan::create(['name' => 'Test'])->id, 'name' => 'Cafe', 'slug' => 'cafe']);
        $user = User::factory()->create(['business_id' => $business->id, 'role' => 'owner']);
        $this->useTenant($business->id);
        $service = app(NotificationService::class);
        $service->send($user, 'new_order', 'New order', 'Order received.', '/#POS', 'order:1');
        $service->send($user, 'new_order', 'New order', 'Order received.', '/#POS', 'order:1');
        foreach (range(2, 25) as $order) {
            $service->send($user, 'new_order', "New order {$order}", 'Order received.', '/#POS', "order:{$order}");
        }

        $this->assertDatabaseCount('notifications', 25);
        $response = $this->actingAs($user)->getJson('/api/notifications')->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('unread_count', 25);
        $id = $response->json('data.0.id');
        $this->actingAs($user)->postJson("/api/notifications/{$id}/read")->assertNoContent();
        $this->assertNotNull($user->notifications()->findOrFail($id)->read_at);
    }

    public function test_customer_cannot_read_another_customers_notification(): void
    {
        $business = Business::create(['plan_id' => Plan::create(['name' => 'Test'])->id, 'name' => 'Cafe', 'slug' => 'cafe', 'active' => true]);
        $this->useTenant($business->id);
        $first = Customer::create(['business_id' => $business->id, 'phone' => '+923001111111']);
        $second = Customer::create(['business_id' => $business->id, 'phone' => '+923002222222']);
        $notification = app(NotificationService::class)->send($second, 'points_earned', 'Points earned', 'You earned points.', '/customer/cafe', 'points:2');

        $this->withSession(['customer_id' => $first->id, 'customer_business_id' => $business->id])
            ->postJson("/api/customer/cafe/notifications/{$notification->id}/read")
            ->assertNotFound();
    }
}
