<?php

namespace App\Services;

use App\Models\PaymentSubmission;

class ReconcileSafepayPayment
{
    public function __construct(
        private SafepayClient $safepay,
        private ActivateSafepaySubscription $activate,
    ) {}

    public function handle(PaymentSubmission $payment): PaymentSubmission
    {
        if ($payment->method !== 'card' || ! $payment->safepay_tracker || $payment->status === 'paid') {
            return $payment;
        }

        $gatewayPayment = $this->safepay->fetchPayment($payment->safepay_tracker);
        $state = data_get($gatewayPayment, 'data.tracker.state') ?? data_get($gatewayPayment, 'data.state');

        if ($state === 'TRACKER_ENDED') {
            $this->activate->handle($payment, $gatewayPayment);
        }

        return $payment->fresh();
    }
}
