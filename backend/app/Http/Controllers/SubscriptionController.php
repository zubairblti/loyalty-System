<?php

namespace App\Http\Controllers;

use App\Models\PaymentSubmission;
use App\Models\Plan;
use App\Services\ReconcileSafepayPayment;
use App\Services\SafepayClient;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function status(Request $request, ReconcileSafepayPayment $reconcile)
    {
        $business = $request->user()->business;
        $processing = $business->payments()->where('status', 'processing')->latest()->first();
        if ($processing) {
            try {
                $reconcile->handle($processing);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return [
            'plan' => Plan::where('active', true)->first(),
            'subscription' => $business->activeSubscription()->with('plan')->first(),
            'payments' => $business->payments()->with('plan')->latest()->limit(10)->get(),
            'customer_portal_url' => config('app.frontend_url')."/customer/{$business->slug}",
            'card_gateway' => [
                'provider' => 'safepay',
                'configured' => filled(config('services.safepay.public_key')) && filled(config('services.safepay.secret_key')),
                'environment' => config('services.safepay.environment'),
            ],
        ];
    }

    public function submitPayment(Request $request)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'method' => ['required', 'in:jazzcash,easypaisa,card'],
            'transaction_reference' => ['required_unless:method,card', 'nullable', 'string', 'max:100'],
            'card_last_four' => ['required_if:method,card', 'nullable', 'digits:4'],
            'receipt' => ['required_unless:method,card', 'nullable', 'image', 'max:4096'],
        ]);
        $plan = Plan::where('active', true)->findOrFail($data['plan_id']);
        $amount = $data['billing_cycle'] === 'yearly'
            ? round($plan->monthly_price * 12 * (1 - $plan->yearly_discount_percent / 100), 2)
            : $plan->monthly_price;
        $path = $request->file('receipt')?->store('payment-receipts', 'local');

        return PaymentSubmission::create([
            ...$data,
            'business_id' => $request->user()->business_id,
            'amount' => $amount,
            'receipt_path' => $path,
            'status' => 'pending',
        ]);
    }

    public function createSafepayCheckout(Request $request, SafepayClient $safepay)
    {
        abort_unless(
            filled(config('services.safepay.public_key')) && filled(config('services.safepay.secret_key')),
            503,
            'Safepay is not configured.',
        );

        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);
        $plan = Plan::where('active', true)->findOrFail($data['plan_id']);
        $amount = $data['billing_cycle'] === 'yearly'
            ? round($plan->monthly_price * 12 * (1 - $plan->yearly_discount_percent / 100), 2)
            : $plan->monthly_price;
        $business = $request->user()->business;

        try {
            if (! $business->safepay_customer_token) {
                $names = preg_split('/\s+/', trim($request->user()->name), 2);
                $business->update([
                    'safepay_customer_token' => $safepay->createCustomer([
                        'first_name' => $names[0],
                        'last_name' => $names[1] ?? $names[0],
                        'email' => $request->user()->email,
                        'phone_number' => $request->user()->phone,
                        'country' => 'PK',
                        'is_guest' => false,
                    ]),
                ]);
            }

            $checkout = $safepay->createCheckout([
                'merchant_api_key' => config('services.safepay.public_key'),
                'user' => $business->safepay_customer_token,
                'intent' => 'CYBERSOURCE',
                'mode' => 'payment',
                'entry_mode' => 'raw',
                'currency' => 'PKR',
                'amount' => (int) round($amount * 100),
                'include_fees' => false,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'payment' => 'Safepay could not start the checkout. Please try again.',
            ]);
        }

        PaymentSubmission::create([
            'business_id' => $request->user()->business_id,
            'plan_id' => $plan->id,
            'billing_cycle' => $data['billing_cycle'],
            'method' => 'card',
            'amount' => $amount,
            'transaction_reference' => $checkout['tracker'],
            'safepay_tracker' => $checkout['tracker'],
            'status' => 'processing',
        ]);

        return [
            ...$checkout,
            'user' => $business->safepay_customer_token,
            'environment' => config('services.safepay.environment'),
        ];
    }

    public function safepayStatus(
        Request $request,
        string $tracker,
        ReconcileSafepayPayment $reconcile,
    ) {
        $payment = $request->user()->business->payments()
            ->where('safepay_tracker', $tracker)
            ->firstOrFail();

        if ($payment->status !== 'approved') {
            try {
                $reconcile->handle($payment);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return [
            'status' => $payment->fresh()->status,
            'active' => $payment->fresh()->status === 'approved',
        ];
    }
}
