<?php

namespace App\Services;

use App\Models\PaymentSubmission;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\BusinessActivatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionManager
{
    public function __construct(private AuditLogger $audit) {}

    public function activate(
        PaymentSubmission $payment,
        ?User $actor = null,
        ?Request $request = null,
        ?string $reason = null,
    ): Subscription {
        return DB::transaction(function () use ($payment, $actor, $request, $reason) {
            $payment = PaymentSubmission::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === 'paid') {
                return Subscription::where('business_id', $payment->business_id)
                    ->where('status', 'active')->latest('id')->firstOrFail();
            }

            $plan = $payment->plan()->firstOrFail();
            $oldPaymentStatus = $payment->status;
            $payment->update([
                'status' => 'paid',
                'reviewed_by' => $actor?->id ?? $payment->reviewed_by,
                'reviewed_at' => now(),
                'payment_date' => $payment->payment_date ?? now(),
                'activation_reason' => $reason ?? $payment->activation_reason,
            ]);
            Subscription::where('business_id', $payment->business_id)
                ->where('status', 'active')->update(['status' => 'replaced']);
            $months = $payment->billing_cycle === 'yearly' ? 12 : max(1, (int) $plan->duration_months);
            $subscription = Subscription::create([
                'business_id' => $payment->business_id,
                'plan_id' => $payment->plan_id,
                'billing_cycle' => $payment->billing_cycle,
                'amount_paid' => $payment->amount,
                'status' => 'active',
                'starts_at' => $payment->payment_date ?? now(),
                'ends_at' => ($payment->payment_date ?? now())->copy()->addMonths($months),
            ]);
            $business = $payment->business;
            $old = $business->only(['plan_id', 'status', 'active']);
            $business->update(['plan_id' => $payment->plan_id, 'status' => 'active', 'active' => true]);
            $business->owner()->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
            $this->audit->log('payment.approved', $payment, ['status' => $oldPaymentStatus], [
                'status' => $payment->status,
                'amount' => $payment->amount,
                'payment_method' => $payment->method,
                'billing_cycle' => $payment->billing_cycle,
                'payment_date' => $payment->payment_date,
                'subscription_start_date' => $subscription->starts_at,
                'subscription_end_date' => $subscription->ends_at,
                'admin_note' => $payment->admin_note,
                'activation_reason' => $payment->activation_reason,
            ], $business->id, $request);
            $this->audit->log('business.activated', $business, $old, $business->only(['plan_id', 'status', 'active']), $business->id, $request);
            $owner = $business->owner()->first();
            $owner?->notify(new BusinessActivatedNotification($business, $subscription->load('plan'), $payment));

            return $subscription;
        });
    }

    public function refund(PaymentSubmission $payment, User $actor, Request $request, ?string $reason = null): PaymentSubmission
    {
        return DB::transaction(function () use ($payment, $actor, $request, $reason) {
            abort_unless($payment->status === 'paid', 422, 'Only paid payments can be refunded.');
            $payment->update([
                'status' => 'refunded',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'admin_note' => $reason ?? $payment->admin_note,
            ]);
            Subscription::where('business_id', $payment->business_id)
                ->where('plan_id', $payment->plan_id)->where('status', 'active')->update(['status' => 'cancelled']);
            $payment->business()->update(['status' => 'expired', 'active' => false]);
            $this->audit->log('payment.refunded', $payment, ['status' => 'paid'], ['status' => 'refunded', 'reason' => $reason], $payment->business_id, $request);

            return $payment->fresh();
        });
    }
}
