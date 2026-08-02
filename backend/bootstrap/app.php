<?php

use App\Http\Middleware\AuthenticateCustomer;
use App\Http\Middleware\RequireActiveSubscription;
use App\Http\Middleware\RequireSuperAdmin;
use App\Http\Middleware\VerifyIntegrationSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'integration.signature' => VerifyIntegrationSignature::class,
            'customer.auth' => AuthenticateCustomer::class,
            'super.admin' => RequireSuperAdmin::class,
            'subscription.active' => RequireActiveSubscription::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
