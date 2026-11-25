<?php

namespace App\Services;

use App\Exceptions\SubResellerLimitReachedException;
use App\Jobs\ProvisionTenant;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvitation;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Parent-created sub-reseller lifecycle (doc 05 §4, doc 02 §7.2).
 *
 * The invitation token lives in the INVITER's tenant; acceptance provisions
 * the owner account in a brand-new child tenant. There is no account merging
 * and no parent mutation right over the child after creation.
 */
class SubResellerInvitationService
{
    public function __construct(
        private readonly ResellerHierarchyService $hierarchy,
        private readonly SubscriptionPlanService $plans,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{business_name: string, owner_name: string, owner_email: string, city: string, gstin?: string|null, note?: string|null}  $data
     * @return array{reseller: Reseller, invitation: UserInvitation, token: string, auto_activated: bool}
     */
    public function invite(
        Reseller $parent,
        User $actor,
        array $data,
        ?Request $request = null,
        bool $overrideLimit = false,
        ?string $overrideReason = null,
    ): array {
        $email = strtolower($data['owner_email']);
        $businessName = trim($data['business_name']);
        $previousTenant = app(TenantContext::class)->get();
        $previousTeamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

        try {
            return DB::transaction(function () use ($parent, $actor, $data, $email, $businessName, $request, $overrideLimit, $overrideReason): array {
                $this->assertNoDuplicate($parent, $email, $businessName);

                $childTenant = Tenant::query()->create([
                    'public_id' => (string) Str::ulid(),
                    'slug' => $this->uniqueSlug($businessName),
                    'name' => $businessName,
                    'legal_name' => $businessName,
                    'type' => 'reseller',
                    'status' => 'pending',
                    'gstin' => $data['gstin'] ?? null,
                    'currency' => 'INR',
                    'timezone' => 'Asia/Kolkata',
                    'locale' => 'en-IN',
                ]);

                app(TenantContext::class)->bind(TenantInfo::fromModel($childTenant));

                $child = Reseller::query()->create([
                    'public_id' => (string) Str::ulid(),
                    'tenant_id' => $childTenant->id,
                    'parent_reseller_id' => $parent->id,
                    'type' => 'sub_reseller',
                    'name' => $businessName,
                    'legal_name' => $businessName,
                    'owner_name' => trim($data['owner_name']),
                    'email' => $email,
                    'gstin' => $data['gstin'] ?? null,
                    'city' => trim($data['city']),
                    'invite_note' => $data['note'] ?? null,
                    'invited_by' => $actor->id,
                    'invited_at' => now(),
                    'kyc_status' => 'minimal',
                    'review_status' => 'pending',
                    'kyc_submitted_at' => null,
                    'review_sla_due_at' => now()->addHours(24),
                    'path' => '/'.$childTenant->id.'/',
                    'effective_status' => 'pending',
                ]);

                $this->hierarchy->ensureSelfRow($child);
                $limit = $overrideLimit ? null : $this->plans->planLimitFor($parent);
                $this->hierarchy->attachChild($parent, $child, $limit);

                // The invitation belongs to the inviter's tenant (doc 05 §4.1).
                app(TenantContext::class)->bind(TenantInfo::fromModel($parent->tenant()->firstOrFail()));

                $plainToken = bin2hex(random_bytes(32));
                $invitation = UserInvitation::query()->create([
                    'type' => 'sub_reseller',
                    'tenant_id' => $parent->tenant_id,
                    'sub_reseller_id' => $child->id,
                    'email' => $email,
                    'roles' => ['reseller.owner'],
                    'invited_by' => $actor->id,
                    'token_hash' => hash('sha256', $plainToken),
                    'status' => 'pending',
                    'expires_at' => now()->addHours((int) config('resellers.invitation_ttl_hours', 168)),
                ]);

                $autoActivated = $this->shouldAutoActivate($parent);
                if ($autoActivated) {
                    $this->activate($child, $actor, $request, $overrideLimit);
                }

                $this->audit->record(
                    'sub_reseller.invited',
                    $child,
                    [
                        'parent_reseller_id' => $parent->id,
                        'owner_email' => $email,
                        'auto_activated' => $autoActivated,
                        'limit_override' => $overrideLimit,
                        'override_reason' => $overrideReason,
                    ],
                    $request,
                    $parent->tenant_id,
                    $actor,
                );

                return [
                    'reseller' => $child,
                    'invitation' => $invitation,
                    'token' => $plainToken,
                    'auto_activated' => $autoActivated,
                ];
            });
        } catch (SubResellerLimitReachedException $exception) {
            throw ValidationException::withMessages(['sub_reseller' => $exception->getMessage()]);
        } finally {
            if ($previousTenant) {
                app(TenantContext::class)->bind($previousTenant);
            } else {
                app(TenantContext::class)->clear();
            }
            app(PermissionRegistrar::class)->setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * Accept an invitation by creating the owner account in the child tenant.
     */
    public function accept(UserInvitation $invitation, Request $request): User
    {
        $child = Reseller::withoutGlobalScopes()->findOrFail($invitation->sub_reseller_id);
        $tenant = $child->tenant()->firstOrFail();
        $this->assertChildAcceptable($child, $tenant);

        $previousTenant = app(TenantContext::class)->get();
        $previousTeamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

            return DB::transaction(function () use ($invitation, $child, $tenant, $validated, $request): User {
                $locked = UserInvitation::withoutGlobalScopes()
                    ->whereKey($invitation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless(
                    $locked->status === 'pending' && ! $locked->expires_at?->isPast(),
                    410,
                    'Invitation is no longer valid',
                );

                $claimed = UserInvitation::withoutGlobalScopes()
                    ->whereKey($locked->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'accepted',
                        'accepted_at' => now(),
                    ]);

                abort_unless($claimed === 1, 410, 'Invitation is no longer valid');

                $user = User::query()
                    ->where('email', strtolower($locked->email))
                    ->where('tenant_id', $tenant->id)
                    ->first();

                if (! $user) {
                    $user = User::query()->create([
                        'tenant_id' => $tenant->id,
                        'name' => $validated['name'],
                        'email' => strtolower($locked->email),
                        'password' => $validated['password'],
                        'status' => 'active',
                    ]);
                }

                Role::findOrCreate('reseller.owner', 'web');
                $user->assignRole('reseller.owner');

                $child->update(['email_verified' => true]);

                $this->audit->record(
                    'invitation.accepted',
                    $locked,
                    [
                        'before' => ['status' => 'pending'],
                        'after' => ['status' => 'accepted'],
                        'sub_reseller_id' => $child->id,
                        'child_tenant_id' => $tenant->id,
                    ],
                    $request,
                    $locked->tenant_id,
                    $user,
                );

                return $user;
            });
        } finally {
            if ($previousTenant) {
                app(TenantContext::class)->bind($previousTenant);
            } else {
                app(TenantContext::class)->clear();
            }
            app(PermissionRegistrar::class)->setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * Invalidate the previous token and issue a fresh single-use link.
     */
    public function resend(UserInvitation $invitation, User $actor, ?Request $request = null): string
    {
        $plainToken = bin2hex(random_bytes(32));
        $previousTenant = app(TenantContext::class)->get();
        $previousTeamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

        try {
            $tenant = $invitation->tenant()->firstOrFail();
            app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

            return DB::transaction(function () use ($invitation, $actor, $plainToken, $request): string {
                $locked = UserInvitation::withoutGlobalScopes()
                    ->whereKey($invitation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless(
                    $locked->status === 'pending' && ! $locked->expires_at?->isPast(),
                    410,
                    'Invitation is no longer valid',
                );

                $revoked = UserInvitation::withoutGlobalScopes()
                    ->whereKey($locked->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'revoked',
                        'revoked_at' => now(),
                    ]);

                abort_unless($revoked === 1, 410, 'Invitation is no longer valid');

                $replacement = UserInvitation::withoutGlobalScopes()->create([
                    'type' => 'sub_reseller',
                    'tenant_id' => $locked->tenant_id,
                    'sub_reseller_id' => $locked->sub_reseller_id,
                    'email' => $locked->email,
                    'roles' => $locked->roles,
                    'invited_by' => $actor->id,
                    'token_hash' => hash('sha256', $plainToken),
                    'status' => 'pending',
                    'expires_at' => now()->addHours((int) config('resellers.invitation_ttl_hours', 168)),
                ]);

                $this->audit->record(
                    'sub_reseller.invitation_resent',
                    $locked,
                    [
                        'sub_reseller_id' => $locked->sub_reseller_id,
                        'new_invitation_id' => $replacement->id,
                    ],
                    $request,
                    $locked->tenant_id,
                    $actor,
                );

                return $plainToken;
            });
        } finally {
            if ($previousTenant) {
                app(TenantContext::class)->bind($previousTenant);
            } else {
                app(TenantContext::class)->clear();
            }
            app(PermissionRegistrar::class)->setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * Provision a pending child (parent within plan limits, or platform).
     */
    public function activate(
        Reseller $child,
        User $actor,
        ?Request $request = null,
        bool $overrideLimit = false,
    ): void {
        if ($child->activated_at) {
            return;
        }

        $parent = Reseller::withoutGlobalScopes()->findOrFail($child->parent_reseller_id);
        $limit = $overrideLimit ? null : $this->plans->planLimitFor($parent);

        if ($limit !== null && $parent->child_count > $limit) {
            throw ValidationException::withMessages([
                'sub_reseller' => 'Parent reseller is at its plan limit.',
            ]);
        }

        $previousTenant = app(TenantContext::class)->get();
        $previousTeamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

        try {
            app(TenantContext::class)->bind(TenantInfo::fromModel($child->tenant()->firstOrFail()));
            app(PermissionRegistrar::class)->setPermissionsTeamId($child->tenant_id);

            DB::transaction(function () use ($child, $actor, $request): void {
                $child->update([
                    'review_status' => 'approved',
                    'reviewed_at' => now(),
                    'reviewer_id' => $actor->id,
                    'activated_at' => now(),
                    'effective_status' => 'active',
                ]);

                $this->audit->record(
                    'sub_reseller.activated',
                    $child,
                    ['tenant_id' => $child->tenant_id, 'parent_reseller_id' => $child->parent_reseller_id],
                    $request,
                    $child->tenant_id,
                    $actor,
                );

                ProvisionTenant::dispatch($child->tenant_id)->afterCommit();
            });
        } finally {
            if ($previousTenant) {
                app(TenantContext::class)->bind($previousTenant);
            } else {
                app(TenantContext::class)->clear();
            }
            app(PermissionRegistrar::class)->setPermissionsTeamId($previousTeamId);
        }
    }

    public function reject(Reseller $child, User $actor, string $reason, ?Request $request = null): void
    {
        $previousTenant = app(TenantContext::class)->get();
        $previousTeamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

        try {
            app(TenantContext::class)->bind(TenantInfo::fromModel($child->tenant()->firstOrFail()));
            app(PermissionRegistrar::class)->setPermissionsTeamId($child->tenant_id);

            DB::transaction(function () use ($child, $actor, $reason, $request): void {
                $child->update([
                    'review_status' => 'rejected',
                    'reviewed_at' => now(),
                    'reviewer_id' => $actor->id,
                    'review_notes' => $reason,
                    'effective_status' => 'rejected',
                ]);
                $child->tenant()->firstOrFail()->update(['status' => 'terminated']);

                $revoked = UserInvitation::withoutGlobalScopes()
                    ->where('type', 'sub_reseller')
                    ->where('sub_reseller_id', $child->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'revoked',
                        'revoked_at' => now(),
                    ]);

                $this->audit->record(
                    'sub_reseller.rejected',
                    $child,
                    ['reason' => $reason, 'invitations_revoked' => $revoked],
                    $request,
                    $child->tenant_id,
                    $actor,
                );
            });
        } finally {
            if ($previousTenant) {
                app(TenantContext::class)->bind($previousTenant);
            } else {
                app(TenantContext::class)->clear();
            }
            app(PermissionRegistrar::class)->setPermissionsTeamId($previousTeamId);
        }
    }

    private function assertChildAcceptable(Reseller $child, Tenant $tenant): void
    {
        abort_unless(
            ! in_array($child->review_status, ['rejected', 'terminated'], true)
                && $tenant->status !== 'terminated',
            410,
            'Invitation is no longer valid',
        );
    }

    private function assertNoDuplicate(Reseller $parent, string $email, string $businessName): void
    {
        if (User::withoutGlobalScopes()
            ->where('tenant_id', $parent->tenant_id)
            ->where('email', $email)
            ->exists()) {
            throw ValidationException::withMessages([
                'owner_email' => 'That email already belongs to a user in your team.',
            ]);
        }

        if (Reseller::withoutGlobalScopes()
            ->where('email', $email)
            ->where('name', $businessName)
            ->exists()) {
            throw ValidationException::withMessages([
                'business_name' => 'A sub-reseller with this owner email and business name already exists.',
            ]);
        }

        if (UserInvitation::withoutGlobalScopes()
            ->where('tenant_id', $parent->tenant_id)
            ->where('type', 'sub_reseller')
            ->where('email', $email)
            ->where('status', 'pending')
            ->exists()) {
            throw ValidationException::withMessages([
                'owner_email' => 'A sub-reseller invitation is already pending for this email. Resend it from the tree instead.',
            ]);
        }
    }

    private function shouldAutoActivate(Reseller $parent): bool
    {
        if (! config('resellers.auto_activate_sub_resellers')) {
            return false;
        }

        // Wallet-balance gating arrives with the money module; suspension
        // cascade is available now via the closure table.
        return $this->hierarchy->effectiveStatus($parent) === 'active';
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'sub-reseller';
        $base = Str::limit($base, 50, '');
        $slug = $base;
        $suffix = 2;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
