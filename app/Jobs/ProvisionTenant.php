<?php

namespace App\Jobs;

use App\Jobs\Contracts\ProvisioningJob;
use App\Jobs\Middleware\TenantQueueMiddleware;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantSetting;
use App\Models\UserInvitation;
use App\Services\AuditLogger;
use App\Services\ResellerHierarchyService;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ProvisionTenant implements ProvisioningJob, ShouldQueue
{
    use \App\Jobs\Concerns\HasTenantContext, Queueable;

    public function __construct(public int $tenant_id) {}

    public function middleware(): array
    {
        return [TenantQueueMiddleware::class];
    }

    public function handle(): void
    {
        $tenant = Tenant::query()->findOrFail($this->tenant_id);
        $previousTenant = app(TenantContext::class)->get();
        $previousTeamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        try {
            DB::transaction(function () use ($tenant): void {
                TenantDomain::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'host' => $tenant->slug.'.'.config('app.tenant_base_domain', 'frenchies.com')],
                    [
                        'type' => 'subdomain',
                        'status' => 'active',
                        'verified_at' => now(),
                    ],
                );

                $defaults = [
                    'invoice_sequence' => ['prefix' => 'FRS', 'next_seq' => 1],
                    'order_sequence' => ['prefix' => 'ORD', 'next_seq' => 1],
                    'invoice.format' => 'FRS-{FY}-{SEQ6}',
                    'maintenance_mode' => false,
                ];
                foreach ($defaults as $key => $value) {
                    TenantSetting::query()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'key' => $key],
                        ['value' => $value, 'type' => 'json'],
                    );
                }

                Role::findOrCreate('reseller.owner', 'web');

                $reseller = Reseller::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->first();

                if ($reseller) {
                    app(ResellerHierarchyService::class)->ensureSelfRow($reseller);
                }

                $hasPendingOwnerInvitation = $reseller && UserInvitation::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('email', strtolower($reseller->email))
                    ->where('status', 'pending')
                    ->exists();

                // A parent-created sub-reseller already has a single-use invitation
                // in the parent's tenant; do not issue a second owner invitation.
                $hasPendingSubResellerInvitation = $reseller && UserInvitation::withoutGlobalScopes()
                    ->where('type', 'sub_reseller')
                    ->where('sub_reseller_id', $reseller->id)
                    ->where('status', 'pending')
                    ->exists();

                if ($reseller && ! $hasPendingOwnerInvitation && ! $hasPendingSubResellerInvitation) {
                    $plainToken = bin2hex(random_bytes(32));
                    UserInvitation::withoutGlobalScopes()->create([
                        'tenant_id' => $tenant->id,
                        'email' => strtolower($reseller->email),
                        'roles' => ['reseller.owner'],
                        'department_grants' => null,
                        'invited_by' => null,
                        'token_hash' => hash('sha256', $plainToken),
                        'status' => 'pending',
                        'expires_at' => now()->addHours(168),
                    ]);
                }
            });

            Cache::put('tenancy:domain:'.$tenant->slug.'.'.config('app.tenant_base_domain', 'frenchies.com'), [
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'domain_type' => 'subdomain',
                'slug' => $tenant->slug,
            ], 300);

            $tenant->update([
                'status' => 'active',
                'provisioned_at' => now(),
            ]);

            app(AuditLogger::class)->record(
                'tenant.provisioned',
                $tenant,
                ['tenant_id' => $tenant->id, 'slug' => $tenant->slug],
            );
        } finally {
            $previousTenant
                ? app(TenantContext::class)->bind($previousTenant)
                : app(TenantContext::class)->clear();
            app(PermissionRegistrar::class)->setPermissionsTeamId($previousTeamId);
        }
    }
}
