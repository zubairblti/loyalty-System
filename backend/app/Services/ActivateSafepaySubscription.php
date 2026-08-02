<?php

namespace App\Services;

use App\Models\PaymentSubmission;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class ActivateSafepaySubscription
{
    public function handle(PaymentSubmission $payment, array $gatewayPayment = []): Subscription
    {
        return DB::transaction(function () use ($payment, $gatewayPayment) {
            $payment = PaymentSubmission::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === 'approved') {
                return Subscription::where('business_id', $payment->business_id)
                    ->where('status', 'active')
                    ->latest('id')
                    ->firstOrFail();
            }

            $lastFour = data_get($gatewayPayment, 'data.action.payment_method.last_four')
                ?? data_get($gatewayPayment, 'action.payment_method.last_four');

            $payment->update([
                'status' => 'approved',
                'card_last_four' => is_string($lastFour) ? substr($lastFour, -4) : $payment->card_last_four,
                'reviewed_at' => now(),
                'admin_note' => 'Verified automatically by Safepay.',
            ]);

            Subscription::where('business_id', $payment->business_id)
                ->where('status', 'active')
                ->update(['status' => 'replaced']);

            $subscription = Subscription::create([
                'business_id' => $payment->business_id,
                'plan_id' => $payment->plan_id,
                'billing_cycle' => $payment->billing_cycle,
                'amount_paid' => $payment->amount,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => $payment->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
            ]);

            $payment->business()->update(['plan_id' => $payment->plan_id, 'active' => true]);

            return $subscription;
        });
    }
}
