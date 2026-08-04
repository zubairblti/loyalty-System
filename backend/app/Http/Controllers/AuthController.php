<?php

namespace App\Http\Controllers;

use App\Mail\VerificationCodeMail;
use App\Models\Business;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\AuditLogger;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        abort_unless(Auth::guard('web')->attempt($credentials, true), 422, 'Invalid credentials.');
        if (! $request->user()->email_verified_at) {
            Auth::guard('web')->logout();
            abort(403, 'Verify your email before signing in.');
        }
        $request->session()->regenerate();

        return $this->me($request);
    }

    public function register(Request $request)
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
        $data['password'] = Hash::make($data['password']);
        $verification = $this->issueCode($data['email'], 'registration', $data);

        return ['sent' => true, 'expires_in' => 120, 'expires_at' => $verification->expires_at->toIso8601String()];
    }

    public function resendRegistration(Request $request)
    {
        $email = $request->validate(['email' => ['required', 'email']])['email'];
        abort_if(User::where('email', $email)->exists(), 422, 'This account is already verified.');
        $pending = VerificationCode::where('email', $email)->where('purpose', 'registration')->latest()->firstOrFail();
        $verification = $this->issueCode($email, 'registration', $pending->payload);

        return ['sent' => true, 'expires_in' => 120, 'expires_at' => $verification->expires_at->toIso8601String()];
    }

    public function verifyRegistration(Request $request, AuditLogger $audit)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'code' => ['required', 'string', 'max:20']]);
        $verification = $this->validCode($data['email'], 'registration', $data['code']);
        $pending = $verification->payload;
        $slugBase = Str::slug($pending['business_name']) ?: 'business';
        $slug = $slugBase;
        $counter = 1;
        while (Business::where('slug', $slug)->exists()) {
            $slug = "{$slugBase}-".++$counter;
        }
        $business = Business::create([
            'name' => $pending['business_name'], 'slug' => $slug, 'plan_id' => null,
            'status' => 'pending', 'active' => false,
        ]);
        $user = User::create([
            'business_id' => $business->id,
            'name' => $pending['name'],
            'email' => $pending['email'],
            'phone' => $pending['phone'],
            'password' => $pending['password'],
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);
        $verification->update(['consumed_at' => now()]);
        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
        $audit->log('business.registered', $business, [], [
            'name' => $business->name,
            'slug' => $business->slug,
            'owner_id' => $user->id,
            'status' => $business->status,
        ], $business->id, $request);

        return $this->me($request);
    }

    public function forgotPassword(Request $request)
    {
        $email = $request->validate(['email' => ['required', 'email', 'exists:users,email']])['email'];
        $verification = $this->issueCode($email, 'password_reset');

        return ['sent' => true, 'expires_in' => 120, 'expires_at' => $verification->expires_at->toIso8601String()];
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $verification = $this->validCode($data['email'], 'password_reset', $data['code']);
        User::where('email', $data['email'])->firstOrFail()->update(['password' => $data['password']]);
        $verification->update(['consumed_at' => now()]);

        return ['reset' => true];
    }

    public function me(Request $request)
    {
        return $request->user()->load('business.plan');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function notifications(Request $request)
    {
        return $request->user()->notifications()->latest()->limit(30)->get();
    }

    public function readNotifications(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->noContent();
    }

    private function issueCode(string $email, string $purpose, ?array $payload = null): VerificationCode
    {
        $code = app()->environment('testing') ? '123456' : (string) random_int(100000, 999999);
        $verification = VerificationCode::create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'payload' => $payload,
            'expires_at' => now()->addMinutes(2),
        ]);
        try {
            Mail::to($email)->queue(new VerificationCodeMail($code, $purpose));
        } catch (\Throwable $exception) {
            $verification->delete();
            Log::warning('Verification email delivery failed.', [
                'email_domain' => str($email)->after('@')->toString(),
                'purpose' => $purpose,
                'mailer_exception' => $exception::class,
            ]);
            abort(503, 'Verification email could not be sent. Please try again shortly or contact support.');
        }
        VerificationCode::where('email', $email)->where('purpose', $purpose)
            ->whereNull('consumed_at')->where('id', '!=', $verification->id)->update(['consumed_at' => now()]);

        return $verification;
    }

    private function validCode(string $email, string $purpose, string $code): VerificationCode
    {
        $normalizedCode = preg_replace('/\D/', '', $code);
        abort_unless(strlen($normalizedCode) === 6, 422, 'Enter the complete 6-digit verification code.');
        $verification = VerificationCode::where('email', $email)->where('purpose', $purpose)
            ->whereNull('consumed_at')->latest('id')->first();
        abort_if(! $verification || $verification->expires_at->isPast() || $verification->attempts >= 5, 422, 'Code is expired.');
        $verification->increment('attempts');
        abort_unless(Hash::check($normalizedCode, $verification->code_hash), 422, 'Invalid verification code.');

        return $verification;
    }
}
