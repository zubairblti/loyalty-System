<?php

namespace App\Http\Controllers;

use App\Models\SafepayWebhookEvent;
use App\Models\PaymentSubmission;
use App\Services\ActivateSafepaySubscription;
use Illuminate\Http\Request;

class SafepayWebhookController extends Controller
{
    public function __invoke(Request $request, ActivateSafepaySubscription $activate)
    {
        $secret = config('services.safepay.webhook_secret');
        abort_unless(filled($secret), 503, 'Safepay webhook secret is not configured.');

        $signature = (string) $request->header('X-SFPY-SIGNATURE');
        $expected = hash_hmac('sha512', $request->getContent(), $secret);
        abort_unless($signature !== '' && hash_equals($expected, $signature), 401, 'Invalid Safepay signature.');

        $payload = $request->validate([
            'token' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', 'max:100'],
            'version' => ['nullable', 'string', 'max:20'],
            'data' => ['required', 'array'],
        ]);
        $tracker = data_get($payload, 'data.tracker.token') ?? data_get($payload, 'data.tracker');
        $event = SafepayWebhookEvent::firstOrCreate(
            ['event_token' => $payload['token']],
            [
                'type' => $payload['type'],
                'version' => $payload['version'] ?? null,
                'tracker' => is_string($tracker) ? $tracker : null,
                'payload' => $payload,
            ],
        );

        if ($event->wasRecentlyCreated && $payload['type'] === 'payment.succeeded' && is_string($tracker)) {
            $payment = PaymentSubmission::where('safepay_tracker', $tracker)->first();
            if ($payment) {
                $activate->handle($payment, $payload);
                $event->update(['processed_at' => now()]);
            }
        }

        return response()->json(['received' => true, 'duplicate' => ! $event->wasRecentlyCreated]);
    }
}
