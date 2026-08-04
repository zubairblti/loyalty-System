<?php

namespace App\Services;

use App\Models\PaymentSubmission;
use App\Models\Subscription;

class ActivateSafepaySubscription
{
    public function __construct(private SubscriptionManager $subscriptions) {}

    public function handle(PaymentSubmission $payment, array $gatewayPayment = []): Subscription
    {
        $lastFour = data_get($gatewayPayment, 'data.action.payment_method.last_four')
            ?? data_get($gatewayPayment, 'action.payment_method.last_four');
        $payment->update([
            'card_last_four' => is_string($lastFour) ? substr($lastFour, -4) : $payment->card_last_four,
            'admin_note' => 'Verified automatically by Safepay.',
            'payment_date' => now(),
            'activation_reason' => 'Safepay confirmed the card payment.',
        ]);

        return $this->subscriptions->activate($payment, reason: $payment->activation_reason);
    }
}
