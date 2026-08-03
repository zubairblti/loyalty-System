<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseAuthenticatedTenant
{
    public function __construct(private TenantContext $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ($user->role === 'super_admin') {
            $this->tenancy->activateSystem();
        } else {
            abort_unless($user->business_id, 403, 'User is not assigned to a business.');
            $this->tenancy->activate((int) $user->business_id);
        }

        try {
            return $next($request);
        } finally {
            $this->tenancy->clear();
        }
    }
}
