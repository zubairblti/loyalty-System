<?php

namespace App\Http\Middleware;

use App\Models\Integration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIntegrationSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $integration = Integration::where('public_key', $request->header('X-Loyalty-Key'))->where('active', true)->firstOrFail();
        $timestamp = (int) $request->header('X-Loyalty-Timestamp');
        abort_if(abs(now()->timestamp - $timestamp) > 300, 401, 'Expired request.');
        $expected = hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", $integration->secret);
        abort_unless(hash_equals($expected, (string) $request->header('X-Loyalty-Signature')), 401, 'Invalid signature.');
        $request->attributes->set('integration', $integration);
        $integration->update(['last_used_at' => now()]);

        return $next($request);
    }
}
