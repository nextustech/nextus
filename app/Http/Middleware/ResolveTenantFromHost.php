<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken()) {
            return $next($request);
        }

        $host = strtolower($request->getHost());
        $context = app(TenantContext::class);

        $reserved = [
            'frenchies.com',
            'www.frenchies.com',
            'app.frenchies.com',
            'api.frenchies.com',
            'localhost',
            '127.0.0.1',
        ];

        $tenant = null;
        if (in_array($host, $reserved, true)) {
            $tenant = Tenant::query()->where('slug', 'central')->first();
        } else {
            $domain = TenantDomain::query()
                ->where('host', $host)
                ->where('status', 'active')
                ->first();

            $tenant = $domain?->tenant;

            if (! $tenant) {
                $baseDomain = config('app.tenant_base_domain', 'frenchies.com');
                if (str_ends_with($host, '.'.$baseDomain)) {
                    $slug = substr($host, 0, -strlen('.'.$baseDomain));
                    $tenant = Tenant::query()->where('slug', $slug)->first();
                }
            }
        }

        if ($tenant) {
            $context->bind(TenantInfo::fromModel($tenant));
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
            $request->attributes->set('tenant_id', $tenant->id);
        }

        return $next($request);
    }
}
