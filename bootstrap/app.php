<?php

use App\Http\Middleware\EnsureTenantReachable;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenantFromHost;
use App\Http\Middleware\ResolveTenantFromToken;
use App\Http\Middleware\ValidateSessionTenantBinding;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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

        // Tenant context MUST be bound before the auth middleware runs: the User
        // model is tenant-scoped, so resolving the authenticated user without a
        // bound tenant makes Auth report "unauthenticated" while the guest
        // middleware (not priority-sorted) reports "authenticated" — an infinite
        // /dashboard <-> /login redirect loop.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: ResolveTenantFromHost::class,
        );

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
