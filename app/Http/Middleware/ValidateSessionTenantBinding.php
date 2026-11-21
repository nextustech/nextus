<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateSessionTenantBinding
{
    public function handle(Request $request, Closure $next): Response
    {
        $boundTenantId = $request->session()->get('tenant_id');
        $resolvedTenant = app(TenantContext::class)->get();

        if ($boundTenantId !== null && $resolvedTenant !== null && (int) $boundTenantId !== $resolvedTenant->id) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'tenant_mismatch');
        }

        if ($resolvedTenant && $request->user()) {
            if ($request->user()->tenant_id !== $resolvedTenant->id) {
                abort(403, 'tenant_mismatch');
            }
        }

        return $next($request);
    }
}
