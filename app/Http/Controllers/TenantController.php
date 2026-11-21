<?php

namespace App\Http\Controllers;

use App\Models\Reseller;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('tenants.view'), 403);

        $validated = $request->validate([
            'type' => ['nullable', 'in:platform,reseller'],
            'status' => ['nullable', 'in:pending,active,suspended,terminated'],
        ]);

        $tenants = Tenant::query()
            ->with('reseller')
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Tenants/Index', [
            'tenants' => $tenants,
            'filters' => $validated,
        ]);
    }

    public function show(Request $request, string $publicId): Response
    {
        abort_unless($request->user()->can('tenants.view'), 403);
        $tenant = Tenant::query()->with(['domains'])->where('public_id', $publicId)->firstOrFail();

        return Inertia::render('Tenants/Show', [
            'tenant' => $tenant,
            'auditLogs' => \App\Models\AuditLog::query()
                ->where('tenant_id', $tenant->id)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function suspend(Request $request, string $publicId)
    {
        abort_unless($request->user()->can('tenants.suspend'), 403);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
        ]);
        $tenant = Tenant::query()->where('public_id', $publicId)->firstOrFail();
        abort_if($tenant->status === 'terminated', 409);

        $before = ['status' => $tenant->status];
        DB::transaction(function () use ($tenant): void {
            $tenant->update([
                'status' => 'suspended',
                'suspended_at' => now(),
            ]);

            Reseller::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->update([
                    'effective_status' => 'suspended',
                ]);

            $tenantUserIds = \App\Models\User::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->pluck('id');

            DB::table('sessions')->whereIn('user_id', $tenantUserIds)->delete();
            \App\Models\User::withoutGlobalScopes()
                ->whereIn('id', $tenantUserIds)
                ->each(fn (\App\Models\User $user) => $user->tokens()->delete());
        });

        app(AuditLogger::class)->record(
            'tenant.suspended',
            $tenant,
            ['before' => $before, 'after' => ['status' => 'suspended'], 'reason' => $validated['reason']],
            $request,
            $tenant->id,
            $request->user(),
        );

        return back();
    }

    public function resume(Request $request, string $publicId)
    {
        abort_unless($request->user()->can('tenants.resume'), 403);
        $tenant = Tenant::query()->where('public_id', $publicId)->firstOrFail();
        abort_if($tenant->status === 'terminated', 409);

        $before = ['status' => $tenant->status];
        $tenant->update([
            'status' => 'active',
            'suspended_at' => null,
        ]);
        Reseller::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->update(['effective_status' => 'active']);

        app(AuditLogger::class)->record(
            'tenant.restored',
            $tenant,
            ['before' => $before, 'after' => ['status' => 'active'], 'reason' => 'Administrator restored tenant'],
            $request,
            $tenant->id,
            $request->user(),
        );

        return back();
    }

    public function terminate(Request $request, string $publicId)
    {
        abort_unless($request->user()->can('tenants.delete'), 403);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
        ]);
        $tenant = Tenant::query()->where('public_id', $publicId)->firstOrFail();

        $before = ['status' => $tenant->status];
        $tenant->update([
            'status' => 'terminated',
            'terminated_at' => now(),
        ]);
        Reseller::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->update(['effective_status' => 'terminated']);

        app(AuditLogger::class)->record(
            'tenant.terminated',
            $tenant,
            ['before' => $before, 'after' => ['status' => 'terminated'], 'reason' => $validated['reason']],
            $request,
            $tenant->id,
            $request->user(),
        );

        return back();
    }
}
