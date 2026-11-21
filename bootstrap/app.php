<?php

use App\Http\Middleware\EnsureTenantReachable;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenantFromHost;
use App\Http\Middleware\ResolveTenantFromToken;
use App\Http\Middleware\ValidateSessionTenantBinding;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            ResolveTenantFromHost::class,
            EnsureTenantReachable::class,
            ValidateSessionTenantBinding::class,
        ]);
        $middleware->alias([
            'tenant.host' => ResolveTenantFromHost::class,
            'tenant.token' => ResolveTenantFromToken::class,
            'tenant.reachable' => EnsureTenantReachable::class,
            'tenant.session' => ValidateSessionTenantBinding::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
