<?php

namespace App\Jobs\Middleware;

use App\Exceptions\TenantContextMissingException;
use App\Jobs\Contracts\ProvisioningJob;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;

class TenantQueueMiddleware
{
    public function handle(object $job, callable $next): void
    {
        $tenantId = $job->tenant_id ?? null;

        if ($tenantId === null) {
            throw new TenantContextMissingException;
        }

        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant) {
            $job->fail('tenant_gone');

            return;
        }

        if ($tenant->status !== 'active' && ! $job instanceof ProvisioningJob) {
            $job->release(300);

            return;
        }

        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));

        try {
            $next($job);
        } finally {
            app(TenantContext::class)->clear();
        }
    }
}
