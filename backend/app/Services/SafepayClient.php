<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SafepayClient
{
    public function createCheckout(array $payload): array
    {
        $tracker = $this->request()->post('/order/payments/v3/', $payload)->throw()->json();

        $trackerToken = data_get($tracker, 'data.tracker.token');
        $authToken = $this->createAuthToken();

        if (! is_string($trackerToken)) {
            throw new RuntimeException('Safepay returned an invalid checkout session.');
        }

        return [
            'tracker' => $trackerToken,
            'auth_token' => $authToken,
        ];
    }

    public function createCustomer(array $payload): string
    {
        $response = $this->request()->post('/user/customers/v1/', $payload)->throw()->json();
        $token = data_get($response, 'data.token');

        if (! is_string($token)) {
            throw new RuntimeException('Safepay returned an invalid customer.');
        }

        return $token;
    }

    public function createAuthToken(): string
    {
        $passport = $this->request()->post('/client/passport/v1/token')->throw()->json();
        $authToken = data_get($passport, 'data');

        if (! is_string($authToken)) {
            throw new RuntimeException('Safepay returned an invalid authentication token.');
        }

        return $authToken;
    }

    public function fetchPayment(string $tracker): array
    {
        return $this->request()
            ->get("/reporter/api/v1/payments/{$tracker}")
            ->throw()
            ->json();
    }

    private function request(): PendingRequest
    {
        $host = config('services.safepay.environment') === 'production'
            ? 'https://api.getsafepay.com'
            : 'https://sandbox.api.getsafepay.com';

        return Http::baseUrl($host)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['x-sfpy-merchant-secret' => config('services.safepay.secret_key')])
            ->timeout(15);
    }
}
