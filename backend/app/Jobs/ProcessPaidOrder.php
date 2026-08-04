<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\LoyaltyService;
use App\Services\NotificationService;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPaidOrder implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId, public int $businessId) {}

    public function handle(LoyaltyService $loyalty, TenantContext $tenancy, ?NotificationService $notifications = null): void
    {
        $notifications ??= app(NotificationService::class);
        $tenancy->activate($this->businessId);
        try {
            $order = Order::findOrFail($this->orderId);
            if ($order->status === 'paid' && $order->customer_id) {
                $entry = $loyalty->earn($order);
                if ($entry) {
                    $order->load(['customer', 'business.owner']);
                    $notifications->send($order->customer, 'points_earned', 'Points earned', "You earned {$entry->points} points from order {$order->external_id}.", "/customer/{$order->business->slug}", "order:{$order->id}:points");
                    $notifications->send($order->customer, 'payment_successful', 'Payment successful', "Order {$order->external_id} was paid successfully.", "/customer/{$order->business->slug}#transactions", "order:{$order->id}:payment-successful");
                    if ($owner = $order->business->owner) {
                        $notifications->send($owner, 'new_order', 'New paid order', "Order {$order->external_id} was paid for PKR ".number_format((float) $order->total, 0).'.', '/', "order:{$order->id}:paid");
                        $notifications->send($owner, 'payment_received', 'Payment received', "PKR ".number_format((float) $order->total, 0)." received for order {$order->external_id}.", '/#Overview', "order:{$order->id}:payment-received");
                    }
                }
            }
        } finally {
            $tenancy->clear();
        }
    }
}
