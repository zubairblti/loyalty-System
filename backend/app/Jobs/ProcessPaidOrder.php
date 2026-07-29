<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\LoyaltyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPaidOrder implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId) {}

    public function handle(LoyaltyService $loyalty): void
    {
        $order = Order::findOrFail($this->orderId);
        if ($order->status === 'paid' && $order->customer_id) {
            $loyalty->earn($order);
        }
    }
}
