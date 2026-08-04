<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_user_can_change_password_with_current_password(): void
    {
        $business = Business::create(['plan_id' => Plan::create(['name' => 'Test'])->id, 'name' => 'Cafe', 'slug' => 'cafe']);
        $user = User::factory()->create(['business_id' => $business->id, 'role' => 'owner', 'password' => Hash::make('old-password')]);
        $this->useTenant($business->id);

        $this->actingAs($user)->putJson('/api/password', ['current_password' => 'old-password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertNoContent();
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_customer_can_reset_a_forgotten_password(): void
    {
        Mail::fake();
        $business = Business::create(['plan_id' => Plan::create(['name' => 'Test'])->id, 'name' => 'Cafe', 'slug' => 'cafe', 'active' => true]);
        $this->useTenant($business->id);
        $customer = Customer::create(['business_id' => $business->id, 'email' => 'member@example.com', 'phone' => '+923001234567', 'password' => 'old-password']);

        $this->postJson('/api/customer/cafe/forgot-password', ['email' => $customer->email])->assertOk();
        $this->postJson('/api/customer/cafe/reset-password', ['email' => $customer->email, 'code' => '123456', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertNoContent();
        $this->assertTrue(Hash::check('new-password', $customer->fresh()->password));
    }
}
