<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantReachable
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(TenantContext::class)->get();

        if ($tenant && $tenant->status !== 'active') {
            if ($request->is('api/*')) {
                abort(403, 'tenant_suspended');
            }

            abort(410, 'Tenant unavailable');
        }

        return $next($request);
    }
}
