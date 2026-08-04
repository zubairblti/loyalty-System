<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCompleteBusinessProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->business?->profile_completed, 428, 'Complete your business profile before accessing the workspace.');

        return $next($request);
    }
}
