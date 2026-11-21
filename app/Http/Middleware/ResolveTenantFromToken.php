<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return $next($request);
        }

        $accessToken = PersonalAccessToken::findToken($bearer);
        if (! $accessToken) {
            abort(401, 'Unauthenticated');
        }

        $tenantId = null;
        foreach ($accessToken->abilities ?? [] as $ability) {
            if (str_starts_with($ability, 'tenant:')) {
                $tenantId = (int) substr($ability, 7);
            }
        }

        if (! $tenantId) {
            abort(403, 'tenant_mismatch');
        }

        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant) {
            abort(403, 'tenant_mismatch');
        }

        $host = strtolower($request->getHost());
        $allowed = $host === 'api.'.config('app.tenant_base_domain', 'frenchies.com')
            || TenantDomain::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->where('host', $host)
                ->exists();

        if (! $allowed && $tenant->type === 'platform' && in_array($host, ['app.'.config('app.tenant_base_domain', 'frenchies.com'), 'localhost', '127.0.0.1'], true)) {
            $allowed = true;
        }

        if (! $allowed) {
            abort(403, 'tenant_mismatch');
        }

        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $request->attributes->set('tenant_id', $tenant->id);

        return $next($request);
    }
}
