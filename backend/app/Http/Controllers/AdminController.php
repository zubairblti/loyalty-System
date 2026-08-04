<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentSubmission;
use App\Models\Plan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ReconcileSafepayPayment;
use App\Services\SubscriptionManager;
use App\Services\NotificationService;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    public function dashboard(ReconcileSafepayPayment $reconcile)
    {
        PaymentSubmission::where('status', 'processing')->whereNotNull('safepay_tracker')
            ->latest()->limit(20)->get()->each(function ($payment) use ($reconcile) {
                try {
                    $reconcile->handle($payment);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });

        return [
            'metrics' => [
                'businesses' => Business::count(),
                'active_businesses' => Business::where('status', 'active')->count(),
                'customers' => Customer::count(),
                'orders' => Order::count(),
                'subscription_revenue' => (float) PaymentSubmission::where('status', 'paid')->sum('amount'),
                'active_subscriptions' => \App\Models\Subscription::where('status', 'active')->where('ends_at', '>', now())->count(),
                'pending_payments' => PaymentSubmission::where('status', 'pending')->count(),
            ],
            'businesses' => Business::with([
                'owner:id,business_id,name,email,phone,email_verified_at',
                'plan' => fn ($query) => $query->withTrashed()->select('id', 'name'),
                'activeSubscription.plan' => fn ($query) => $query->withTrashed()->select('id', 'name'),
                'payments' => fn ($query) => $query->whereNotIn('status', ['initiated', 'abandoned'])->with('plan:id,name')->latest()->limit(10),
            ])->withCount(['customers', 'orders'])->latest()->limit(200)->get(),
            'plans' => Plan::withTrashed()->withCount('businesses')->orderBy('display_order')->orderBy('id')->get(),
            'pending_payments' => PaymentSubmission::with(['business:id,name,slug', 'plan:id,name'])
                ->where('status', 'pending')->latest()->get(),
            'payments' => PaymentSubmission::with(['business:id,name,slug', 'plan' => fn ($query) => $query->withTrashed()->select('id', 'name')])
                ->whereNotIn('status', ['initiated', 'abandoned'])->latest()->limit(500)->get(),
            'payment_metrics' => [
                'paid_total' => (float) PaymentSubmission::where('status', 'paid')->sum('amount'),
                'processing_total' => (float) PaymentSubmission::where('status', 'processing')->sum('amount'),
                'paid_count' => PaymentSubmission::where('status', 'paid')->count(),
                'card_total' => (float) PaymentSubmission::where('status', 'paid')->where('method', 'card')->sum('amount'),
            ],
            'charts' => $this->chartData(),
        ];
    }

    public function updateProfile(Request $request, AuditLogger $audit)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'admin_brand_name' => ['required', 'string', 'max:80'],
            'admin_brand_subtitle' => ['required', 'string', 'max:80'],
            'current_password' => ['nullable', 'string'],
        ]);
        if ($data['email'] !== $user->email) {
            abort_unless(Hash::check($data['current_password'] ?? '', $user->password), 422, 'Current password is required to change the email address.');
        }
        $old = $user->only(['name', 'email', 'admin_brand_name', 'admin_brand_subtitle']);
        unset($data['current_password']);
        $user->update($data);
        $audit->log('admin.profile_updated', $user, $old, $user->fresh()->only(array_keys($data)), null, $request);

        return $user->fresh();
    }

    public function activity(Request $request)
    {
        $filters = $this->auditFilters($request);
        $base = AuditLog::query()->whereHas('actor', fn ($query) => $query->where('role', 'super_admin'));

        return $this->filteredAuditResponse($base, $filters);
    }

    public function businessActivity(Request $request, Business $business)
    {
        $filters = $this->auditFilters($request);
        $logs = $this->applyAuditFilters(AuditLog::query()->where('business_id', $business->id), $filters)
            ->with(['actor:id,name,role', 'business:id,name'])->latest('id')->get()
            ->unique(fn ($log) => implode('|', [$log->action, $log->actor_id, json_encode($log->old_values), json_encode($log->new_values)]))
            ->values();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $paginator = new LengthAwarePaginator($logs->forPage($page, 20)->values(), $logs->count(), 20, $page);

        return response()->json([
            ...$paginator->toArray(),
            'filters' => $this->auditFilterOptions(AuditLog::query()->where('business_id', $business->id)),
        ]);
    }

    public function createBusiness(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $data['phone'] = PhoneNumber::normalize($data['phone']);
        validator($data, ['phone' => ['required', 'unique:users,phone']])->validate();

        return DB::transaction(function () use ($data, $request, $audit) {
            $base = Str::slug($data['business_name']) ?: 'business';
            $slug = $base;
            for ($suffix = 2; Business::where('slug', $slug)->exists(); $suffix++) {
                $slug = "{$base}-{$suffix}";
            }
            $business = Business::create([
                'name' => $data['business_name'], 'slug' => $slug, 'plan_id' => null,
                'status' => 'pending', 'active' => false,
            ]);
            $owner = User::create([
                'business_id' => $business->id, 'name' => $data['name'], 'email' => $data['email'],
                'phone' => $data['phone'], 'password' => Hash::make($data['password']), 'role' => 'owner',
                'email_verified_at' => now(),
            ]);
            $audit->log('business.created_by_admin', $business, [], [
                'name' => $business->name, 'status' => $business->status, 'owner_id' => $owner->id,
            ], $business->id, $request);

            return response()->json($business->load('owner'), 201);
        });
    }

    public function businesses(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['pending', 'active', 'suspended', 'expired', 'rejected'])],
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return Business::query()
            ->with(['owner:id,business_id,name,email,phone,email_verified_at', 'plan' => fn ($query) => $query->withTrashed()->select('id', 'name')])
            ->withCount(['customers', 'orders'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $term = '%'.strtolower($search).'%';
                $query->where(function ($nested) use ($term) {
                    $nested->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereHas('owner', fn ($owner) => $owner
                            ->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                            ->orWhere('phone', 'like', $term));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['plan_id'] ?? null, fn ($query, int $planId) => $query->where('plan_id', $planId))
            ->latest()->paginate(50);
    }

    public function updateBusiness(Request $request, Business $business, AuditLogger $audit, NotificationService $notifications)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'active', 'suspended', 'expired', 'rejected'])],
            'reason' => ['required_unless:status,active', 'nullable', 'string', 'max:500'],
        ]);
        $old = $business->only(['status', 'active']);
        $business->update(['status' => $data['status'], 'active' => $data['status'] === 'active']);
        $audit->log('business.status_changed', $business, $old, [
            ...$business->only(['status', 'active']), 'reason' => $data['reason'] ?? null,
        ], $business->id, $request);
        if ($owner = $business->owner()->first()) {
            $type = $data['status'] === 'active' ? 'business_reactivated' : "business_{$data['status']}";
            $notifications->send($owner, $type, $data['status'] === 'active' ? 'Business reactivated' : 'Business status updated', "{$business->name} is now {$data['status']}.", '/#Overview', "business:{$business->id}:status:{$data['status']}:{$business->updated_at->timestamp}");
        }

        return $business->fresh()->load('plan');
    }

    public function businessDetail(Business $business)
    {
        return $business->load([
            'owner:id,business_id,name,email,phone,email_verified_at,created_at',
            'plan' => fn ($query) => $query->withTrashed(),
            'subscriptions' => fn ($query) => $query->with(['plan' => fn ($plan) => $plan->withTrashed()])->latest('starts_at'),
            'payments' => fn ($query) => $query->whereNotIn('status', ['initiated', 'abandoned'])
                ->with(['plan' => fn ($plan) => $plan->withTrashed()])->latest(),
        ])->loadCount(['customers', 'orders']);
    }

    private function auditFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'action' => ['nullable', 'string', 'max:100'],
            'business_id' => ['nullable', 'integer', 'exists:businesses,id'],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function filteredAuditResponse($base, array $filters)
    {
        $optionsBase = clone $base;
        $logs = $this->applyAuditFilters($base, $filters)
            ->with(['actor:id,name,role', 'business:id,name'])->latest('id')->paginate(20);

        return response()->json([...$logs->toArray(), 'filters' => $this->auditFilterOptions($optionsBase)]);
    }

    private function applyAuditFilters($query, array $filters)
    {
        return $query
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $term = '%'.strtolower($search).'%';
                $query->where(function ($nested) use ($term) {
                    $nested->whereRaw('LOWER(action) LIKE ?', [$term])
                        ->orWhereHas('business', fn ($business) => $business->whereRaw('LOWER(name) LIKE ?', [$term]))
                        ->orWhereHas('actor', fn ($actor) => $actor->whereRaw('LOWER(name) LIKE ?', [$term]));
                });
            })
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['business_id'] ?? null, fn ($query, $id) => $query->where('business_id', $id))
            ->when($filters['actor_id'] ?? null, fn ($query, $id) => $query->where('actor_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
    }

    private function auditFilterOptions($query): array
    {
        $actionIds = (clone $query)->whereNotNull('action')->distinct()->orderBy('action')->pluck('action');
        $businessIds = (clone $query)->whereNotNull('business_id')->distinct()->pluck('business_id');
        $actorIds = (clone $query)->whereNotNull('actor_id')->distinct()->pluck('actor_id');

        return [
            'actions' => $actionIds,
            'businesses' => Business::whereIn('id', $businessIds)->orderBy('name')->get(['id', 'name']),
            'actors' => User::whereIn('id', $actorIds)->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function chartData(): array
    {
        $months = collect(range(5, 0))->map(fn ($offset) => now()->startOfMonth()->subMonths($offset));
        return [
            'monthly' => $months->map(fn ($month) => [
                'label' => $month->format('M Y'),
                'businesses' => Business::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count(),
                'revenue' => (float) PaymentSubmission::where('status', 'paid')->whereBetween('payment_date', [$month, $month->copy()->endOfMonth()])->sum('amount'),
            ])->values(),
            'plans' => Plan::withTrashed()->withCount(['businesses' => fn ($query) => $query->where('status', 'active')])
                ->orderBy('display_order')->get(['id', 'name'])->map(fn ($plan) => ['name' => $plan->name, 'businesses' => $plan->businesses_count])->values(),
        ];
    }

    public function storePlan(Request $request, AuditLogger $audit)
    {
        $plan = Plan::create($this->planData($request));
        $audit->log('plan.created', $plan, [], $plan->toArray(), null, $request);
        if ($plan->active) {
            $audit->log('plan.activated', $plan, ['active' => false], ['active' => true], null, $request);
        }

        return response()->json($plan, 201);
    }

    public function updatePlan(Request $request, int $plan, AuditLogger $audit)
    {
        $plan = Plan::withTrashed()->findOrFail($plan);
        $old = $plan->toArray();
        $plan->update($this->planData($request, $plan));
        $audit->log('plan.updated', $plan, $old, $plan->fresh()->toArray(), null, $request);
        if ((bool) $old['active'] !== $plan->active) {
            $audit->log($plan->active ? 'plan.activated' : 'plan.deactivated', $plan,
                ['active' => (bool) $old['active']], ['active' => $plan->active], null, $request);
        }

        return $plan->fresh();
    }

    public function deletePlan(Request $request, int $plan, AuditLogger $audit)
    {
        $plan = Plan::findOrFail($plan);
        abort_if($plan->businesses()->where('status', 'active')->exists(), 422, 'An active business is using this plan. Disable the plan instead.');
        $plan->delete();
        $audit->log('plan.deleted', $plan, ['deleted_at' => null], ['deleted_at' => now()], null, $request);

        return response()->noContent();
    }

    public function restorePlan(Request $request, int $plan, AuditLogger $audit)
    {
        $plan = Plan::onlyTrashed()->findOrFail($plan);
        $plan->restore();
        $audit->log('plan.restored', $plan, [], ['deleted_at' => null], null, $request);

        return $plan->fresh();
    }

    public function reviewPayment(Request $request, int $payment, SubscriptionManager $subscriptions, AuditLogger $audit)
    {
        $payment = PaymentSubmission::findOrFail($payment);
        $data = $request->validate([
            'status' => ['required', Rule::in(['paid', 'failed', 'refunded'])],
            'admin_note' => ['nullable', 'string', 'max:500'],
            'activation_reason' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['nullable', 'date'],
        ]);
        if ($data['status'] === 'refunded') {
            return $subscriptions->refund($payment, $request->user(), $request, $data['admin_note'] ?? null);
        }
        abort_unless(in_array($payment->status, ['pending', 'processing'], true), 422, 'Payment has already been reviewed.');
        if ($data['status'] === 'paid') {
            $payment->update([
                'admin_note' => $data['admin_note'] ?? null,
                'payment_date' => $data['payment_date'] ?? now(),
                'activation_reason' => $data['activation_reason'] ?? 'Payment verified by Super Admin.',
            ]);

            $subscriptions->activate($payment, $request->user(), $request, $payment->activation_reason);

            return $payment->fresh(['business', 'plan']);
        }
        $old = ['status' => $payment->status];
        $payment->update([
            'status' => 'failed', 'admin_note' => $data['admin_note'] ?? null,
            'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
        ]);
        $audit->log('payment.failed', $payment, $old, $payment->only(['status', 'admin_note']), $payment->business_id, $request);

        return $payment->fresh(['business', 'plan']);
    }

    public function recordCash(Request $request, Business $business, SubscriptionManager $subscriptions)
    {
        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')->whereNull('deleted_at')],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_date' => ['nullable', 'date'],
            'admin_note' => ['nullable', 'string', 'max:500'],
            'activation_reason' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $plan = Plan::where('active', true)->findOrFail($data['plan_id']);
        $calculated = $data['billing_cycle'] === 'yearly'
            ? round($plan->monthly_price * 12 * (1 - $plan->yearly_discount_percent / 100), 2)
            : $plan->monthly_price;
        $amount = round((float) ($data['amount'] ?? $calculated), 2);
        $paymentDate = Carbon::parse($data['payment_date'] ?? now())->startOfDay();
        $fingerprint = hash('sha256', implode('|', [
            $business->id, 'cash', $plan->id, $data['billing_cycle'], number_format($amount, 2, '.', ''), $paymentDate->toDateString(),
        ]));

        return DB::transaction(function () use ($business, $plan, $data, $amount, $paymentDate, $fingerprint, $subscriptions, $request) {
            Business::whereKey($business->id)->lockForUpdate()->firstOrFail();
            $retry = PaymentSubmission::where('idempotency_key', $data['idempotency_key'])->first();
            if ($retry) {
                abort_unless($retry->business_id === $business->id, 409, 'This payment request key is already in use.');

                return $retry->load(['business', 'plan']);
            }
            abort_if(PaymentSubmission::where('payment_fingerprint', $fingerprint)->exists(), 409, 'This cash payment has already been recorded.');

            $payment = PaymentSubmission::create([
                'business_id' => $business->id, 'plan_id' => $plan->id,
                'billing_cycle' => $data['billing_cycle'], 'method' => 'cash',
                'amount' => $amount, 'currency' => 'PKR', 'status' => 'pending',
                'payment_date' => $paymentDate, 'admin_note' => $data['admin_note'] ?? null,
                'activation_reason' => $data['activation_reason'] ?? 'Cash payment verified by Super Admin.',
                'idempotency_key' => $data['idempotency_key'], 'payment_fingerprint' => $fingerprint,
            ]);
            $subscriptions->activate($payment, $request->user(), $request, $payment->activation_reason);

            return $payment->fresh(['business', 'plan']);
        });
    }

    public function paymentReceipt(int $payment)
    {
        $payment = PaymentSubmission::findOrFail($payment);
        abort_unless($payment->receipt_path && Storage::disk('local')->exists($payment->receipt_path), 404);

        return response()->file(Storage::disk('local')->path($payment->receipt_path));
    }

    private function planData(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('plans')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'yearly_discount_percent' => ['required', 'integer', 'min:0', 'max:90'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:120'],
            'features' => ['required', 'array', 'min:1'], 'features.*' => ['required', 'string', 'max:150'],
            'domain_limit' => ['required', 'integer', 'min:0'], 'qr_limit' => ['required', 'integer', 'min:0'],
            'terminal_limit' => ['required', 'integer', 'min:0'], 'monthly_order_limit' => ['required', 'integer', 'min:1'],
            'active' => ['required', 'boolean'], 'public' => ['required', 'boolean'],
            'display_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);
    }
}
