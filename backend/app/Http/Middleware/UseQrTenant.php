<?php

namespace App\Http\Middleware;

use App\Models\QrCode;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseQrTenant
{
    public function __construct(private TenantContext $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $qr = $this->tenancy->runAsSystem(fn () => QrCode::withoutGlobalScope('tenant')
            ->where('token_hash', hash('sha256', (string) $request->route('token')))->firstOrFail());
        $this->tenancy->activate($qr->business_id);

        try {
            return $next($request);
        } finally {
            $this->tenancy->clear();
        }
    }
}
