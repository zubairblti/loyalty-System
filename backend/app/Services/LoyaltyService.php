<?php

namespace App\Services;

use App\Events\PointsUpdated;
use App\Models\Business;
use App\Models\Order;
use App\Models\PointsLedger;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function earn(Order $order): PointsLedger
    {
        return DB::transaction(function () use ($order) {
            $business = Business::findOrFail($order->business_id);
            $points = (int) floor(((float) $order->total / 100) * $business->points_per_100);
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
                PointsUpdated::dispatch($order->business_id, $order->customer_id, $this->balance($order->customer_id));
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
}
