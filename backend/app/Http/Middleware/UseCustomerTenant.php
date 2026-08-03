<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseCustomerTenant
{
    public function __construct(private TenantContext $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $business = Business::where('slug', $request->route('slug'))->where('active', true)->firstOrFail();
        $this->tenancy->activate($business->id);
        $request->attributes->set('tenant_business', $business);

        try {
            return $next($request);
        } finally {
            $this->tenancy->clear();
        }
    }
}
