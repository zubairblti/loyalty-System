<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $business = $request->user()?->business;
        abort_unless($business->activeSubscription()->exists(), 402, 'An active subscription is required.');
        abort_unless($business?->active && $business->status === 'active', 403, 'Business account is inactive.');

        return $next($request);
    }
}
