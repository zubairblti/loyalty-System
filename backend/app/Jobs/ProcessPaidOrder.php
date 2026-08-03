<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\LoyaltyService;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPaidOrder implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId, public int $businessId) {}

    public function handle(LoyaltyService $loyalty, TenantContext $tenancy): void
    {
        $tenancy->activate($this->businessId);
        try {
            $order = Order::findOrFail($this->orderId);
            if ($order->status === 'paid' && $order->customer_id) {
                $loyalty->earn($order);
            }
        } finally {
            $tenancy->clear();
        }
    }
}
