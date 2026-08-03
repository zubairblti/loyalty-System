<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentSubmission;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\ReconcileSafepayPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function dashboard(ReconcileSafepayPayment $reconcile)
    {
        PaymentSubmission::where('status', 'processing')
            ->whereNotNull('safepay_tracker')
            ->latest()
            ->limit(20)
            ->get()
            ->each(function ($payment) use ($reconcile) {
                try {
                    $reconcile->handle($payment);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });

        return [
            'metrics' => [
                'businesses' => Business::count(),
                'active_businesses' => Business::where('active', true)->count(),
                'customers' => Customer::count(),
                'orders' => Order::count(),
                'revenue_processed' => (float) Order::where('status', 'paid')->sum('total'),
            ],
            'businesses' => Business::with(['plan:id,name', 'activeSubscription.plan:id,name', 'payments' => fn ($query) => $query->latest()->limit(3)])
                ->withCount(['customers', 'orders'])
                ->latest()
                ->limit(20)
                ->get(),
            'plans' => Plan::withCount('businesses')->get(),
            'pending_payments' => PaymentSubmission::with(['business:id,name,slug', 'plan:id,name'])
                ->where('status', 'pending')->latest()->get(),
            'payments' => PaymentSubmission::with(['business:id,name,slug', 'plan:id,name'])
                ->latest()
                ->limit(200)
                ->get(),
            'payment_metrics' => [
                'approved_total' => (float) PaymentSubmission::where('status', 'approved')->sum('amount'),
                'processing_total' => (float) PaymentSubmission::where('status', 'processing')->sum('amount'),
                'approved_count' => PaymentSubmission::where('status', 'approved')->count(),
                'card_total' => (float) PaymentSubmission::where('status', 'approved')->where('method', 'card')->sum('amount'),
            ],
        ];
    }

    public function updateBusiness(Request $request, Business $business)
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $business->update($data);

        return $business->load('plan');
    }

    public function savePlan(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'yearly_discount_percent' => ['required', 'integer', 'min:0', 'max:90'],
            'features' => ['required', 'array', 'min:1'],
            'features.*' => ['required', 'string', 'max:150'],
            'domain_limit' => ['required', 'integer', 'min:0'],
            'qr_limit' => ['required', 'integer', 'min:0'],
            'terminal_limit' => ['required', 'integer', 'min:0'],
            'monthly_order_limit' => ['required', 'integer', 'min:1'],
        ]);
        $plan = Plan::first();
        if ($plan) {
            $plan->update($data);
        } else {
            $plan = Plan::create($data);
        }

        return $plan;
    }

    public function reviewPayment(Request $request, int $payment)
    {
        $payment = PaymentSubmission::findOrFail($payment);
        $data = $request->validate(['status' => ['required', 'in:approved,rejected'], 'admin_note' => ['nullable', 'string', 'max:500']]);
        abort_unless($payment->status === 'pending', 422, 'Payment has already been reviewed.');

        return DB::transaction(function () use ($payment, $data, $request) {
            $payment->update([
                ...$data,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            if ($data['status'] === 'approved') {
                Subscription::where('business_id', $payment->business_id)->where('status', 'active')->update(['status' => 'replaced']);
                Subscription::create([
                    'business_id' => $payment->business_id,
                    'plan_id' => $payment->plan_id,
                    'billing_cycle' => $payment->billing_cycle,
                    'amount_paid' => $payment->amount,
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => $payment->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                ]);
                $payment->business()->update(['plan_id' => $payment->plan_id, 'active' => true]);
            }

            return $payment->fresh(['business', 'plan']);
        });
    }

    public function recordCash(Request $request, Business $business)
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id'], 'billing_cycle' => ['required', 'in:monthly,yearly']]);
        $plan = Plan::findOrFail($data['plan_id']);
        $amount = $data['billing_cycle'] === 'yearly'
            ? round($plan->monthly_price * 12 * (1 - $plan->yearly_discount_percent / 100), 2)
            : $plan->monthly_price;
        $payment = PaymentSubmission::create([
            ...$data, 'business_id' => $business->id, 'method' => 'cash', 'amount' => $amount, 'status' => 'pending',
        ]);
        $request->merge(['status' => 'approved', 'admin_note' => 'Cash payment recorded by Super Admin.']);

        return $this->reviewPayment($request, $payment->id);
    }

    public function paymentReceipt(int $payment)
    {
        $payment = PaymentSubmission::findOrFail($payment);
        abort_unless($payment->receipt_path && Storage::disk('local')->exists($payment->receipt_path), 404);

        return response()->file(Storage::disk('local')->path($payment->receipt_path));
    }
}
