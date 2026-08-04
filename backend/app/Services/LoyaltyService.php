<?php

namespace App\Services;

use App\Events\PointsUpdated;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerMembership;
use App\Models\LoyaltyPointRule;
use App\Models\LoyaltySetting;
use App\Models\MembershipLevel;
use App\Models\Order;
use App\Models\PointsLedger;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function earn(Order $order): ?PointsLedger
    {
        return DB::transaction(function () use ($order) {
            $business = Business::findOrFail($order->business_id);
            $settings = LoyaltySetting::where('business_id', $business->id)->first();
            if (! $settings?->loyalty_enabled || ! $settings->points_enabled) {
                return null;
            }
            $rule = LoyaltyPointRule::where('active', true)->where('purchase_amount', '<=', $order->total)
                ->orderByDesc('purchase_amount')->first();
            if (! $rule) {
                return null;
            }
            $points = (int) floor((float) $order->total / (float) $rule->purchase_amount) * $rule->earned_points;
            $entry = PointsLedger::firstOrCreate(
                ['business_id' => $business->id, 'idempotency_key' => "order:{$order->id}:earn"],
                [
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->id,
                    'points' => $points,
                    'type' => 'earn',
                    'description' => "Points from order {$order->external_id}",
                ],
            );
            if ($entry->wasRecentlyCreated) {
                $balance = $this->balance($order->customer_id);
                $this->membership(Customer::findOrFail($order->customer_id), $balance);
                PointsUpdated::dispatch($order->business_id, $order->customer_id, $balance);
            }

            return $entry;
        });
    }

    public function reverse(Order $order): PointsLedger
    {
        $earned = PointsLedger::where('order_id', $order->id)->where('type', 'earn')->firstOrFail();

        return PointsLedger::firstOrCreate(
            ['business_id' => $order->business_id, 'idempotency_key' => "order:{$order->id}:reverse"],
            ['customer_id' => $order->customer_id, 'order_id' => $order->id, 'points' => -$earned->points, 'type' => 'reversal', 'description' => 'Order refunded'],
        );
    }

    public function balance(int $customerId): int
    {
        return (int) PointsLedger::where('customer_id', $customerId)->sum('points');
    }

    public function membership(Customer $customer, int $points): array
    {
        return DB::transaction(function () use ($customer, $points) {
            $settings = LoyaltySetting::where('business_id', $customer->business_id)->first();
            $levels = MembershipLevel::where('active', true)->orderBy('required_points')->get();
            $eligible = $levels->where('required_points', '<=', $points)->last();
            $assignment = CustomerMembership::with('level')->where('customer_id', $customer->id)
                ->whereNull('ended_at')->lockForUpdate()->first();

            if (! $assignment && $eligible) {
                $assignment = $this->assign($customer, $eligible, 'points_earned');
            } elseif ($assignment) {
                $currentLevel = $assignment->level;
                if ($eligible && $eligible->required_points > $currentLevel->required_points) {
                    $assignment = $this->replace($assignment, $eligible, 'tier_upgrade');
                } elseif ($points >= $currentLevel->required_points) {
                    if ($assignment->grace_expires_at) {
                        $assignment->update(['grace_expires_at' => null]);
                    }
                } else {
                    $graceDays = $settings?->membership_downgrade_grace_days;
                    if ($graceDays && ! $assignment->grace_expires_at) {
                        $assignment->update(['grace_expires_at' => now()->addDays($graceDays)]);
                    } elseif ($graceDays && $assignment->grace_expires_at?->isPast()) {
                        $assignment = $eligible
                            ? $this->replace($assignment, $eligible, 'grace_period_expired')
                            : $this->end($assignment, 'grace_period_expired');
                    } elseif (! $graceDays && $assignment->grace_expires_at) {
                        $assignment->update(['grace_expires_at' => null]);
                    }
                }
            }

            $current = $assignment?->level;
            $nextFloor = max($points, $current?->required_points ?? -1);
            $next = $levels->first(fn (MembershipLevel $level) => $level->required_points > $nextFloor);

            return [
                'current' => $current,
                'next' => $next,
                'grace_expires_at' => $assignment?->grace_expires_at,
                'is_grace_period' => (bool) $assignment?->grace_expires_at,
            ];
        });
    }

    private function assign(Customer $customer, MembershipLevel $level, string $reason): CustomerMembership
    {
        return CustomerMembership::create([
            'business_id' => $customer->business_id,
            'customer_id' => $customer->id,
            'membership_level_id' => $level->id,
            'assigned_at' => now(),
            'assignment_reason' => $reason,
        ])->load('level');
    }

    private function replace(CustomerMembership $current, MembershipLevel $level, string $reason): CustomerMembership
    {
        $customer = $current->customer;
        $current->update(['ended_at' => now(), 'end_reason' => $reason, 'grace_expires_at' => null]);

        return $this->assign($customer, $level, $reason);
    }

    private function end(CustomerMembership $current, string $reason): ?CustomerMembership
    {
        $current->update(['ended_at' => now(), 'end_reason' => $reason, 'grace_expires_at' => null]);

        return null;
    }
}
