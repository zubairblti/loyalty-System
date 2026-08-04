<?php

namespace App\Http\Controllers;

use App\Contracts\SmsSender;
use App\Mail\VerificationCodeMail;
use App\Models\Business;
use App\Models\Customer;
use App\Models\LoyaltySetting;
use App\Models\VerificationCode;
use App\Services\LoyaltyService;
use App\Services\NotificationService;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CustomerPortalController extends Controller
{
    public function business(string $slug)
    {
        $business = Business::where('slug', $slug)->where('active', true)->firstOrFail();

        return $this->brandedBusiness($business);
    }

    public function logo(string $slug)
    {
        $business = Business::where('slug', $slug)->where('active', true)->firstOrFail();
        abort_unless($business->brand_logo_path && Storage::disk('local')->exists($business->brand_logo_path), 404);

        return response()->file(Storage::disk('local')->path($business->brand_logo_path), ['Cache-Control' => 'public, max-age=3600']);
    }

    public function register(Request $request, string $slug)
    {
        $business = Business::where('slug', $slug)->where('active', true)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('customers')->where('business_id', $business->id)],
            'phone' => ['required', 'string', 'max:30', Rule::unique('customers')->where('business_id', $business->id)],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        try {
            $data['phone'] = PhoneNumber::normalize($data['phone']);
        } catch (\InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }
        abort_if(Customer::where('business_id', $business->id)->where('phone', $data['phone'])->exists(), 422, 'This mobile number is already registered.');
        $data['business_id'] = $business->id;
        $data['password'] = Hash::make($data['password']);
        $verification = $this->issueRegistrationCode($business, $data);

        return response()->json([
            'sent' => true,
            'expires_in' => 120,
            'expires_at' => $verification->expires_at->toIso8601String(),
        ]);
    }

    public function resendRegistration(Request $request, string $slug)
    {
        $business = Business::where('slug', $slug)->where('active', true)->firstOrFail();
        $email = $request->validate(['email' => ['required', 'email']])['email'];
        abort_if(Customer::where('business_id', $business->id)->where('email', $email)->exists(), 422, 'This account is already verified.');
        $pending = VerificationCode::where('email', $email)->where('purpose', $this->registrationPurpose($business))
            ->whereNull('consumed_at')->latest()->firstOrFail();
        $verification = $this->issueRegistrationCode($business, $pending->payload);

        return ['sent' => true, 'expires_in' => 120, 'expires_at' => $verification->expires_at->toIso8601String()];
    }

    public function verifyRegistration(Request $request, string $slug, NotificationService $notifications)
    {
        $business = Business::where('slug', $slug)->where('active', true)->firstOrFail();
        $data = $request->validate(['email' => ['required', 'email'], 'code' => ['required', 'digits:6']]);
        $verification = VerificationCode::where('email', $data['email'])
            ->where('purpose', $this->registrationPurpose($business))->whereNull('consumed_at')->latest()->first();
        abort_if(! $verification || $verification->expires_at->isPast() || $verification->attempts >= 5, 422, 'Code is expired.');
        $verification->increment('attempts');
        abort_unless(Hash::check($data['code'], $verification->code_hash), 422, 'Invalid verification code.');
        abort_if(Customer::where('business_id', $business->id)->where(function ($query) use ($verification) {
            $query->where('email', $verification->payload['email'])->orWhere('phone', $verification->payload['phone']);
        })->exists(), 422, 'A customer account already exists with these details.');

        $customer = Customer::create($verification->payload + ['email_verified_at' => now()]);
        $verification->update(['consumed_at' => now()]);
        $this->startSession($request, $customer);
        if ($owner = $business->owner()->first()) {
            $notifications->send($owner, 'customer_registered', 'New customer registered', "{$customer->name} joined {$business->name} rewards.", '/#Customers', "customer:{$customer->id}:registered");
        }
        $notifications->send($customer, 'profile_updated', 'Welcome to rewards', "Your {$business->name} rewards profile is ready.", "/customer/{$business->slug}#dashboard", "customer:{$customer->id}:welcome");

        return $this->dashboard($request, app(LoyaltyService::class));
    }

    public function login(Request $request, string $slug)
    {
        $business = Business::where('slug', $slug)->where('active', true)->firstOrFail();
        $data = $request->validate(['phone' => ['required', 'string', 'max:30'], 'password' => ['required', 'string'], 'remember' => ['sometimes', 'boolean']]);
        try {
            $data['phone'] = PhoneNumber::normalize($data['phone']);
        } catch (\InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }
        $customer = Customer::where('business_id', $business->id)->where('phone', $data['phone'])->first();
        abort_unless($customer && $customer->password && Hash::check($data['password'], $customer->password), 422, 'Invalid phone number or password.');
        abort_unless($customer->email_verified_at, 403, 'Verify your email before signing in.');
        $this->startSession($request, $customer);
        $response = response()->json($this->dashboard($request, app(LoyaltyService::class)));
        if ($data['remember'] ?? false) {
            $token = Str::random(60);
            $customer->update(['remember_token' => hash('sha256', $token)]);
            $response->cookie('customer_remember', "{$customer->id}|{$token}", 43200, '/', null, config('session.secure'), true, false, 'lax');
        }

        return $response;
    }

    public function forgotPassword(Request $request, string $slug)
    {
        $business = Business::where('slug', $slug)->where('active', true)->firstOrFail();
        $email = $request->validate(['email' => ['required', 'email']])['email'];
        $customer = Customer::where('business_id', $business->id)->where('email', $email)->firstOrFail();
        $purpose = "customer_password_reset:{$business->id}";
        $code = app()->environment('testing') ? '123456' : (string) random_int(100000, 999999);
        $verification = VerificationCode::create(['email' => $email, 'purpose' => $purpose, 'code_hash' => Hash::make($code), 'payload' => ['customer_id' => $customer->id], 'expires_at' => now()->addMinutes(2)]);
        Mail::to($email)->queue(new VerificationCodeMail($code, $purpose));
        VerificationCode::where('email', $email)->where('purpose', $purpose)->whereNull('consumed_at')->whereKeyNot($verification->id)->update(['consumed_at' => now()]);

        return ['sent' => true, 'expires_at' => $verification->expires_at->toIso8601String()];
    }

    public function resetPassword(Request $request, string $slug)
    {
        $business = Business::where('slug', $slug)->where('active', true)->firstOrFail();
        $data = $request->validate(['email' => ['required', 'email'], 'code' => ['required', 'digits:6'], 'password' => ['required', 'confirmed', Password::min(8)]]);
        $verification = VerificationCode::where('email', $data['email'])->where('purpose', "customer_password_reset:{$business->id}")->whereNull('consumed_at')->latest()->first();
        abort_if(! $verification || $verification->expires_at->isPast() || $verification->attempts >= 5, 422, 'Code is expired.');
        $verification->increment('attempts');
        abort_unless(Hash::check($data['code'], $verification->code_hash), 422, 'Invalid verification code.');
        $customer = Customer::where('business_id', $business->id)->findOrFail($verification->payload['customer_id']);
        $customer->update(['password' => $data['password'], 'remember_token' => null]);
        $verification->update(['consumed_at' => now()]);

        return response()->noContent()->withoutCookie('customer_remember');
    }

    private function startSession(Request $request, Customer $customer): void
    {
        $request->session()->regenerate();
        $request->session()->put(['customer_id' => $customer->id, 'customer_business_id' => $customer->business_id]);
    }

    private function registrationPurpose(Business $business): string
    {
        return "customer_registration:{$business->id}";
    }

    private function issueRegistrationCode(Business $business, array $payload): VerificationCode
    {
        $key = "customer-registration:{$business->id}:{$payload['email']}";
        abort_if(RateLimiter::tooManyAttempts($key, 3), 429, 'Please wait before requesting another code.');
        RateLimiter::hit($key, 60);
        $purpose = $this->registrationPurpose($business);
        $code = app()->environment('testing') ? '123456' : (string) random_int(100000, 999999);
        $verification = VerificationCode::create([
            'email' => $payload['email'],
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'payload' => $payload,
            'expires_at' => now()->addMinutes(2),
        ]);
        try {
            Mail::to($payload['email'])->queue(new VerificationCodeMail($code, $purpose));
        } catch (\Throwable $exception) {
            Log::warning('Customer verification email failed.', [
                'business_id' => $business->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
        try {
            app(SmsSender::class)->send(
                $payload['phone'],
                "Your {$business->name} rewards verification code is {$code}. It expires in 2 minutes.",
            );
        } catch (\Throwable $exception) {
            Log::warning('Customer verification SMS failed.', [
                'business_id' => $business->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
        VerificationCode::where('email', $payload['email'])->where('purpose', $purpose)
            ->whereNull('consumed_at')->whereKeyNot($verification->id)->update(['consumed_at' => now()]);

        return $verification;
    }

    public function dashboard(Request $request, LoyaltyService $loyalty)
    {
        /** @var Customer $customer */
        $customer = $request->attributes->get('customer')
            ?? Customer::findOrFail($request->session()->get('customer_id'));
        $balance = $loyalty->balance($customer->id);
        $settings = LoyaltySetting::where('business_id', $customer->business_id)->first();
        $loyaltyEnabled = (bool) $settings?->loyalty_enabled;
        $membershipsEnabled = $loyaltyEnabled && (bool) $settings?->memberships_enabled;
        $membership = $membershipsEnabled ? $loyalty->membership($customer, $balance) : ['current' => null, 'next' => null, 'grace_expires_at' => null, 'is_grace_period' => false];

        return [
            'customer' => $customer,
            'business' => $this->brandedBusiness(Business::findOrFail($customer->business_id)),
            'balance' => $balance,
            'loyalty' => ['enabled' => $loyaltyEnabled, 'points_enabled' => $loyaltyEnabled && (bool) $settings?->points_enabled, 'memberships_enabled' => $membershipsEnabled],
            'tier' => $membership['current']?->name,
            'tier_details' => $membership['current'],
            'next_tier' => $membership['next']?->name,
            'next_tier_at' => $membership['next']?->required_points,
            'membership_grace_expires_at' => $membership['grace_expires_at'],
            'membership_in_grace_period' => $membership['is_grace_period'],
            'transactions' => $customer->ledger()->with('order:id,external_id,total')->latest()->limit(30)->get(),
            'orders' => $customer->orders()->latest()->limit(10)->get(),
        ];
    }

    public function logout(Request $request)
    {
        $request->attributes->get('customer')?->update(['remember_token' => null]);
        $request->session()->forget(['customer_id', 'customer_business_id']);
        $request->session()->regenerateToken();

        return response()->noContent()->withoutCookie('customer_remember');
    }

    public function updateProfile(Request $request, NotificationService $notifications)
    {
        /** @var Customer $customer */
        $customer = $request->attributes->get('customer');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
        ]);
        $customer->update($data);
        $notifications->send($customer, 'profile_updated', 'Profile updated', 'Your profile details were updated successfully.', "/customer/{$request->route('slug')}#profile", 'customer:'.$customer->id.':profile:'.$customer->updated_at->timestamp);

        return $customer;
    }

    public function updatePassword(Request $request, NotificationService $notifications)
    {
        $customer = $request->attributes->get('customer');
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        abort_unless($customer->password && Hash::check($data['current_password'], $customer->password), 422, 'Current password is incorrect.');
        $customer->update(['password' => $data['password'], 'remember_token' => null]);
        $notifications->send($customer, 'profile_updated', 'Password updated', 'Your account password was changed successfully.', "/customer/{$request->route('slug')}#profile", 'customer:'.$customer->id.':password:'.$customer->updated_at->timestamp);

        return response()->noContent()->withoutCookie('customer_remember');
    }

    public function notifications(Request $request)
    {
        $customer = $request->attributes->get('customer');
        $paginator = $customer->notifications()->latest()->paginate(20);

        return response()->json([
            ...$paginator->toArray(),
            'unread_count' => $customer->unreadNotifications()->count(),
        ]);
    }

    public function readNotifications(Request $request)
    {
        $request->attributes->get('customer')->unreadNotifications->markAsRead();

        return response()->noContent();
    }

    public function readNotification(Request $request, string $notification)
    {
        $item = $request->attributes->get('customer')->notifications()->findOrFail($notification);
        $item->markAsRead();

        return response()->noContent();
    }

    public function broadcastingAuth(Request $request)
    {
        $customer = $request->attributes->get('customer');
        $request->setUserResolver(fn () => $customer);

        return Broadcast::auth($request);
    }

    private function brandedBusiness(Business $business): array
    {
        return [
            'id' => $business->id,
            'name' => $business->name,
            'slug' => $business->slug,
            'currency' => $business->currency,
            'points_per_100' => $business->points_per_100,
            'brand_name' => $business->brand_name ?: $business->name,
            'brand_primary_color' => $business->brand_primary_color ?: '#123e63',
            'brand_accent_color' => $business->brand_accent_color ?: '#16805a',
            'brand_text_color' => $business->brand_text_color ?: '#ffffff',
            'logo_url' => $business->brand_logo_path ? "/api/customer/{$business->slug}/logo?v=".rawurlencode(basename($business->brand_logo_path)) : null,
        ];
    }
}
