<?php

namespace App\Http\Middleware;

use App\Models\Integration;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIntegrationSignature
{
    public function __construct(private TenantContext $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $integration = $this->tenancy->runAsSystem(fn () => Integration::withoutGlobalScope('tenant')
            ->where('public_key', $request->header('X-Loyalty-Key'))->where('active', true)->firstOrFail());
        $timestamp = (int) $request->header('X-Loyalty-Timestamp');
        abort_if(abs(now()->timestamp - $timestamp) > 300, 401, 'Expired request.');
        $expected = hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", $integration->secret);
        abort_unless(hash_equals($expected, (string) $request->header('X-Loyalty-Signature')), 401, 'Invalid signature.');
        $request->attributes->set('integration', $integration);
        $this->tenancy->activate($integration->business_id);

        try {
            $integration->update(['last_used_at' => now()]);

            return $next($request);
        } finally {
            $this->tenancy->clear();
        }
    }
}
