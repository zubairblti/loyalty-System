<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerOtp;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class CustomerPortalController extends Controller
{
    public function business(string $slug)
    {
        return Business::where('slug', $slug)->where('active', true)
            ->firstOrFail(['id', 'name', 'slug', 'currency', 'points_per_100']);
    }

    public function requestOtp(Request $request, string $slug)
    {
        $business = Business::where('slug', $slug)->where('active', true)->firstOrFail();
        $phone = $request->validate(['phone' => ['required', 'string', 'max:30']])['phone'];
        $key = "customer-otp:{$business->id}:{$phone}:{$request->ip()}";

        abort_if(RateLimiter::tooManyAttempts($key, 3), 429, 'Please wait before requesting another code.');
        RateLimiter::hit($key, 60);

        $isDemoEnvironment = app()->environment(['local', 'testing']);
        $code = $isDemoEnvironment ? '123456' : (string) random_int(100000, 999999);
        CustomerOtp::create([
            'business_id' => $business->id,
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
        ]);

        // Production SMS providers should receive this code through a queued notification.
        return response()->json([
            'sent' => true,
            'expires_in' => 300,
            'demo_code' => $isDemoEnvironment ? $code : null,
        ]);
    }

    public function verifyOtp(Request $request, string $slug)
    {
        $business = Business::where('slug', $slug)->where('active', true)->firstOrFail();
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'code' => ['required', 'digits:6'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);
        $otp = CustomerOtp::where('business_id', $business->id)
            ->where('phone', $data['phone'])->whereNull('consumed_at')->latest()->first();

        abort_if(! $otp || $otp->expires_at->isPast() || $otp->attempts >= 5, 422, 'Code is expired.');
        $otp->increment('attempts');
        abort_unless(Hash::check($data['code'], $otp->code_hash), 422, 'Invalid code.');

        $otp->update(['consumed_at' => now()]);
        $customer = Customer::firstOrCreate(
            ['business_id' => $business->id, 'phone' => $data['phone']],
            ['name' => $data['name'] ?? null],
        );
        $request->session()->regenerate();
        $request->session()->put(['customer_id' => $customer->id, 'customer_business_id' => $business->id]);

        return $this->dashboard($request, app(LoyaltyService::class));
    }

    public function dashboard(Request $request, LoyaltyService $loyalty)
    {
        /** @var Customer $customer */
        $customer = $request->attributes->get('customer')
            ?? Customer::findOrFail($request->session()->get('customer_id'));
        $balance = $loyalty->balance($customer->id);
        [$tier, $nextTier, $nextAt] = match (true) {
            $balance >= 1000 => ['Gold', null, null],
            $balance >= 500 => ['Silver', 'Gold', 1000],
            default => ['Member', 'Silver', 500],
        };

        return [
            'customer' => $customer,
            'business' => Business::findOrFail($customer->business_id, ['id', 'name', 'slug', 'currency']),
            'balance' => $balance,
            'tier' => $tier,
            'next_tier' => $nextTier,
            'next_tier_at' => $nextAt,
            'transactions' => $customer->ledger()->with('order:id,external_id,total')->latest()->limit(30)->get(),
            'orders' => $customer->orders()->latest()->limit(10)->get(),
        ];
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['customer_id', 'customer_business_id']);
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
