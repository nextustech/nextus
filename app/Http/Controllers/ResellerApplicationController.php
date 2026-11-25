<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionTenant;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ResellerApplicationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Resellers/Register');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'legal_name' => ['nullable', 'string', 'max:191'],
            'slug' => ['required', 'regex:/^[a-z0-9-]{3,60}$/', 'unique:tenants,slug'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'pan' => ['required', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'gstin_unregistered_declaration' => ['required', 'boolean'],
            'business_address' => ['required', 'string', 'max:500'],
            'bank_proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'aup_accepted' => ['accepted'],
            'dpdp_accepted' => ['accepted'],
        ]);

        if (empty($validated['gstin']) && ! $validated['gstin_unregistered_declaration']) {
            return back()->withErrors([
                'gstin' => 'Provide GSTIN or declare that the business is unregistered.',
            ]);
        }

        $artifactPath = $request->file('bank_proof')->store('kyc/bank-proofs', 'local');

        $tenant = null;
        $reseller = DB::transaction(function () use ($validated, $artifactPath, &$tenant): Reseller {
            $tenant = Tenant::query()->create([
                'public_id' => (string) Str::ulid(),
                'slug' => strtolower($validated['slug']),
                'name' => $validated['name'],
                'legal_name' => $validated['legal_name'] ?? null,
                'type' => 'reseller',
                'status' => 'pending',
                'gstin' => $validated['gstin'] ?? null,
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'locale' => 'en-IN',
            ]);

            app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));

            return Reseller::query()->create([
                'public_id' => (string) Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => $validated['name'],
                'legal_name' => $validated['legal_name'] ?? null,
                'gstin' => $validated['gstin'] ?? null,
                'email' => strtolower($validated['email']),
                'phone' => $validated['phone'],
                'pan' => strtoupper($validated['pan']),
                'business_address' => $validated['business_address'],
                'bank_proof_path' => $artifactPath,
                'gstin_unregistered_declaration' => $validated['gstin_unregistered_declaration'],
                'email_verified' => false,
                'phone_verified' => false,
                'aup_accepted_at' => now(),
                'dpdp_accepted_at' => now(),
                'kyc_status' => 'standard',
                'review_status' => 'pending',
                'kyc_submitted_at' => now(),
                'review_sla_due_at' => now()->addHours(24),
                'path' => '/'.$tenant->id.'/',
                'effective_status' => 'active',
            ]);
        });

        app(\App\Services\ResellerHierarchyService::class)->ensureSelfRow($reseller);

        app(AuditLogger::class)->record(
            'tenant.lifecycle.changed',
            $tenant,
            [
                'before' => ['status' => null],
                'after' => ['status' => 'pending', 'review_status' => 'pending'],
                'reason' => 'Reseller application submitted',
            ],
            $request,
            $tenant?->id,
            null,
        );

        return redirect()->route('resellers.registered', ['publicId' => $reseller->public_id]);
    }

    public function registered(string $publicId): Response
    {
        $reseller = Reseller::withoutGlobalScopes()
            ->where('public_id', $publicId)
            ->firstOrFail();
        abort_unless($reseller->review_status === 'pending', 404);

        return Inertia::render('Resellers/Registered', [
            'application' => [
                'public_id' => $reseller->public_id,
                'status' => $reseller->review_status,
                'sla_due_at' => $reseller->review_sla_due_at,
            ],
        ]);
    }

    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()->can('resellers.view')
            && $request->user()->can('resellers.approve'),
            403,
        );

        $applications = Reseller::withoutGlobalScopes()
            ->whereIn('review_status', ['pending', 'info_requested'])
            ->orderBy('review_sla_due_at')
            ->get();

        $parents = Reseller::withoutGlobalScopes()
            ->whereIn('id', $applications->pluck('parent_reseller_id')->filter()->unique())
            ->get(['id', 'name', 'tenant_id']);

        $parentNames = $parents->pluck('name', 'id');
        $parentTenantStatuses = Tenant::query()
            ->whereIn('id', $parents->pluck('tenant_id')->filter()->unique())
            ->pluck('status', 'id');

        $applications = $applications
            ->map(fn (Reseller $reseller) => [
                'public_id' => $reseller->public_id,
                'name' => $reseller->name,
                'email' => $reseller->email,
                'type' => $reseller->type,
                'parent_name' => $parentNames[$reseller->parent_reseller_id] ?? null,
                'kyc_status' => $reseller->kyc_status,
                'review_status' => $reseller->review_status,
                'review_sla_due_at' => $reseller->review_sla_due_at?->toIso8601String(),
                'submitted_age' => $reseller->created_at?->diffForHumans(),
                'completeness' => $this->completeness($reseller),
                'risk_flags' => $this->riskFlags($reseller, $parents, $parentTenantStatuses),
                'details' => [
                    'business_name' => $reseller->name,
                    'owner_name' => $reseller->owner_name,
                    'owner_email' => $reseller->email,
                    'city' => $reseller->city,
                    'gstin' => $reseller->gstin,
                    'pan' => $reseller->pan,
                    'business_address' => $reseller->business_address,
                    'note' => $reseller->invite_note,
                ],
            ])
            ->all();

        return Inertia::render('Resellers/Index', [
            'applications' => $applications,
            'canApprove' => $request->user()->can('resellers.approve'),
        ]);
    }

    public function approve(Request $request, string $publicId)
    {
        abort_unless($request->user()->can('resellers.approve'), 403);
        $request->validate(['review_notes' => ['nullable', 'string', 'max:1000']]);
        $reseller = Reseller::withoutGlobalScopes()->where('public_id', $publicId)->firstOrFail();

        if ($reseller->kyc_status !== 'standard') {
            return back()->withErrors(['kyc' => 'Standard KYC is required before approval.']);
        }

        $before = ['status' => $reseller->tenant->status, 'review_status' => $reseller->review_status];
        $reseller->update([
            'review_status' => 'approved',
            'reviewed_at' => now(),
            'reviewer_id' => $request->user()->id,
            'review_notes' => $request->string('review_notes')->toString() ?: null,
        ]);
        $reseller->tenant->update(['status' => 'pending']);
        (new ProvisionTenant($reseller->tenant_id))->handle();

        app(AuditLogger::class)->record(
            'tenant.lifecycle.changed',
            $reseller->tenant,
            ['before' => $before, 'after' => ['status' => 'active', 'review_status' => 'approved']],
            $request,
            $reseller->tenant_id,
            $request->user(),
        );

        return back();
    }

    public function requestInfo(Request $request, string $publicId)
    {
        abort_unless($request->user()->can('resellers.approve'), 403);
        $request->validate(['review_notes' => ['required', 'string', 'min:20', 'max:1000']]);
        $reseller = Reseller::withoutGlobalScopes()->where('public_id', $publicId)->firstOrFail();

        $reseller->update([
            'review_status' => 'info_requested',
            'review_notes' => $request->string('review_notes')->toString(),
            'reviewer_id' => $request->user()->id,
        ]);

        app(AuditLogger::class)->record(
            'tenant.lifecycle.changed',
            $reseller,
            [
                'before' => ['review_status' => 'pending'],
                'after' => ['review_status' => 'info_requested'],
                'reason' => $request->string('review_notes')->toString(),
            ],
            $request,
            $reseller->tenant_id,
            $request->user(),
        );

        return back();
    }

    public function reject(Request $request, string $publicId)
    {
        abort_unless($request->user()->can('resellers.approve'), 403);
        $request->validate(['review_notes' => ['required', 'string', 'min:20', 'max:1000']]);
        $reseller = Reseller::withoutGlobalScopes()->where('public_id', $publicId)->firstOrFail();

        $reseller->update([
            'review_status' => 'rejected',
            'reviewed_at' => now(),
            'reviewer_id' => $request->user()->id,
            'review_notes' => $request->string('review_notes')->toString(),
        ]);
        $reseller->tenant->update(['status' => 'terminated']);

        app(AuditLogger::class)->record(
            'tenant.lifecycle.changed',
            $reseller->tenant,
            [
                'before' => ['status' => 'pending', 'review_status' => 'pending'],
                'after' => ['status' => 'terminated', 'review_status' => 'rejected'],
                'reason' => $request->string('review_notes')->toString(),
            ],
            $request,
            $reseller->tenant_id,
            $request->user(),
        );

        return back();
    }

    private function completeness(Reseller $reseller): int
    {
        $fields = $reseller->type === 'sub_reseller'
            ? [
                $reseller->name,
                $reseller->email,
                $reseller->owner_name,
                $reseller->city,
                $reseller->parent_reseller_id,
            ]
            : [
                $reseller->name,
                $reseller->email,
                $reseller->phone,
                $reseller->pan,
                $reseller->business_address,
                $reseller->gstin ?: $reseller->gstin_unregistered_declaration,
                $reseller->aup_accepted_at,
                $reseller->dpdp_accepted_at,
            ];

        $filled = collect($fields)
            ->filter(fn ($value) => $value !== null && $value !== '' && $value !== false)
            ->count();

        return (int) round($filled / count($fields) * 100);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Reseller>  $parents
     * @param  \Illuminate\Support\Collection<int, string>  $parentTenantStatuses
     * @return list<string>
     */
    private function riskFlags(Reseller $reseller, $parents, $parentTenantStatuses): array
    {
        $flags = [];

        if ($reseller->gstin && Reseller::withoutGlobalScopes()
            ->where('gstin', $reseller->gstin)
            ->where('review_status', 'approved')
            ->exists()) {
            $flags[] = 'duplicate_gstin';
        }

        if (preg_match('/@(mailinator|guerrillamail|tempmail|throwaway)\./i', (string) $reseller->email)) {
            $flags[] = 'disposable_email';
        }

        $parent = $reseller->parent_reseller_id
            ? $parents->firstWhere('id', $reseller->parent_reseller_id)
            : null;

        if ($parent && $parentTenantStatuses[$parent->tenant_id] !== 'active') {
            $flags[] = 'parent_suspended';
        }

        return $flags;
    }
}
