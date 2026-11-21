<?php

namespace App\Http\Controllers;

use App\Models\Reseller;
use App\Models\Tenant;
use App\Models\UserInvitation;
use App\Notifications\SubResellerInvitationCreated;
use App\Services\ResellerHierarchyService;
use App\Services\SubResellerInvitationService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubResellerController extends Controller
{
    public function __construct(
        private readonly ResellerHierarchyService $hierarchy,
        private readonly SubResellerInvitationService $invitations,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('resellers.hierarchy_view'), 403);

        $parent = $this->currentReseller();
        $members = $parent
            ? $this->hierarchy->subtree($parent)
            : Reseller::withoutGlobalScopes()->get()->all();

        $parentNames = Reseller::withoutGlobalScopes()
            ->whereIn('id', collect($members)->pluck('parent_reseller_id')->filter()->unique())
            ->pluck('name', 'id');

        $descendants = collect($members)
            ->where('id', '!=', $parent?->id)
            ->map(fn (Reseller $member) => [
                'public_id' => $member->public_id,
                'name' => $member->name,
                'depth' => (int) ($member->depth ?? 0),
                'status' => $member->effective_status,
                'review_status' => $member->review_status,
                'joined_at' => $member->created_at?->toDateString(),
                'parent_name' => $parentNames[$member->parent_reseller_id] ?? null,
                'owner_name' => $member->owner_name,
                'owner_email' => $member->email,
                'city' => $member->city,
                'gstin' => $member->gstin,
                'invite_note' => $member->invite_note,
            ])
            ->values()
            ->all();

        $pendingInvitations = UserInvitation::withoutGlobalScopes()
            ->where('type', 'sub_reseller')
            ->where('tenant_id', app(TenantContext::class)->id())
            ->where('status', 'pending')
            ->with(['subReseller' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'public_id', 'name')])
            ->orderByDesc('created_at')
            ->get(['id', 'sub_reseller_id', 'email', 'status', 'expires_at', 'created_at'])
            ->map(fn (UserInvitation $invitation) => [
                'id' => $invitation->id,
                'business_name' => $invitation->subReseller?->name,
                'public_id' => $invitation->subReseller?->public_id,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('Resellers/SubResellers', [
            'parent' => $parent ? ['public_id' => $parent->public_id, 'name' => $parent->name] : null,
            'descendants' => $descendants,
            'pendingInvitations' => $pendingInvitations,
            'canInvite' => $request->user()->can('resellers.create') && $request->user()->can('resellers.hierarchy_manage'),
            'canApprove' => $request->user()->can('resellers.approve'),
            'autoActivation' => (bool) config('resellers.auto_activate_sub_resellers'),
            'maxSubResellers' => $parent ? app(\App\Services\SubscriptionPlanService::class)->planLimitFor($parent) : null,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless(
            $request->user()->can('resellers.create') && $request->user()->can('resellers.hierarchy_manage'),
            403,
        );

        $parent = $this->currentReseller();

        return Inertia::render('Resellers/SubResellerInvite', [
            'parent' => $parent?->only(['public_id', 'name']),
            'requiresParentSelection' => $parent === null && app(TenantContext::class)->isPlatform(),
            'parentDepth' => $parent ? $this->hierarchy->depthOf($parent) : 0,
            'autoActivation' => (bool) config('resellers.auto_activate_sub_resellers'),
            'canOverrideLimit' => app(TenantContext::class)->isPlatform() && $request->user()->can('resellers.approve'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(
            $request->user()->can('resellers.create') && $request->user()->can('resellers.hierarchy_manage'),
            403,
        );

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:100'],
            'owner_name' => ['required', 'string', 'max:100'],
            'owner_email' => ['required', 'email', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'note' => ['nullable', 'string', 'max:500'],
            'parent_public_id' => ['nullable', 'string'],
            'override_limit' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'min:20', 'max:1000', 'required_if:override_limit,true'],
        ]);

        $overrideLimit = (bool) ($validated['override_limit'] ?? false);
        abort_unless(
            ! $overrideLimit
                || (app(TenantContext::class)->isPlatform() && $request->user()->can('resellers.approve')),
            403,
        );

        $parent = $this->resolveParent($validated);

        // Doc 05 §4.3: re-inviting the same owner + business routes back to the
        // existing record instead of creating a second child.
        $existing = Reseller::withoutGlobalScopes()
            ->where('email', strtolower($validated['owner_email']))
            ->where('name', trim($validated['business_name']))
            ->first();

        if ($existing && ($existing->parent_reseller_id === $parent->id || app(TenantContext::class)->isPlatform())) {
            return redirect()->route('resellers.sub-resellers.index', [
                'existing' => $existing->public_id,
            ])->with('status', 'That sub-reseller already exists. Open it in the tree to resend the invitation.');
        }

        $result = $this->invitations->invite(
            $parent,
            $request->user(),
            $validated,
            $request,
            $overrideLimit,
            $validated['override_reason'] ?? null,
        );

        Notification::route('mail', strtolower($validated['owner_email']))
            ->notify(new SubResellerInvitationCreated(
                $result['token'],
                $result['reseller']->name,
                $request->user()->name,
            ));

        return back()->with('status', $result['auto_activated']
            ? 'Sub-reseller invited and activated.'
            : 'Sub-reseller invited. Awaiting platform review.');
    }

    public function showAccept(Request $request, string $token): Response
    {
        $invitation = $this->findInvitation($token);
        $child = Reseller::withoutGlobalScopes()->findOrFail($invitation->sub_reseller_id);

        return Inertia::render('Resellers/AcceptSubResellerInvitation', [
            'token' => $token,
            'businessName' => $child->name,
            'ownerEmail' => $invitation->email,
            'inviter' => \App\Models\User::withoutGlobalScopes()->find($invitation->invited_by)?->name,
            'authenticated' => $request->user() !== null,
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $owner = $this->invitations->accept($this->findInvitation($token), $request);
        $tenant = Tenant::query()->findOrFail($owner->tenant_id);

        // A pending child is unreachable until provisioning completes. Show a
        // confirmation instead of redirecting the owner to a 410 dashboard.
        if ($tenant->status !== 'active') {
            return redirect()->route('sub-reseller-invitations.accepted', ['token' => $token]);
        }

        Auth::login($owner);
        $request->session()->regenerate();
        $request->session()->put('tenant_id', $owner->tenant_id);

        return redirect()->to(
            'http://'.$tenant->slug.'.'.config('app.tenant_base_domain', 'frenchies.com').'/dashboard',
        );
    }

    public function accepted(string $token): Response
    {
        $invitation = UserInvitation::withoutGlobalScopes()
            ->where('type', 'sub_reseller')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        $child = Reseller::withoutGlobalScopes()->findOrFail($invitation->sub_reseller_id);

        return Inertia::render('Resellers/SubResellerInvitationAccepted', [
            'businessName' => $child->name,
            'ownerEmail' => $invitation->email,
            'pendingReview' => $child->review_status === 'pending',
        ]);
    }

    public function resend(Request $request, string $publicId)
    {
        abort_unless($request->user()->can('resellers.create'), 403);

        $child = $this->findChild($publicId);
        $this->authorizeRelationship($request, $child);

        $invitation = UserInvitation::withoutGlobalScopes()
            ->where('type', 'sub_reseller')
            ->where('sub_reseller_id', $child->id)
            ->where('status', 'pending')
            ->latest()
            ->firstOrFail();

        $token = $this->invitations->resend($invitation, $request->user(), $request);

        Notification::route('mail', $invitation->email)
            ->notify(new SubResellerInvitationCreated($token, $child->name, $request->user()->name));

        return back()->with('status', 'Invitation resent.');
    }

    public function approve(Request $request, string $publicId)
    {
        abort_unless($request->user()->can('resellers.approve'), 403);

        $child = $this->findChild($publicId);
        $this->authorizeRelationship($request, $child);
        $this->invitations->activate($child, $request->user(), $request);

        return back()->with('status', 'Sub-reseller approved and provisioned.');
    }

    public function reject(Request $request, string $publicId)
    {
        abort_unless($request->user()->can('resellers.approve') && app(TenantContext::class)->isPlatform(), 403);

        $validated = $request->validate(['reason' => ['required', 'string', 'min:20', 'max:1000']]);
        $child = $this->findChild($publicId);
        $this->invitations->reject($child, $request->user(), $validated['reason'], $request);

        return back()->with('status', 'Sub-reseller application rejected.');
    }

    private function findInvitation(string $token): UserInvitation
    {
        $invitation = UserInvitation::withoutGlobalScopes()
            ->where('type', 'sub_reseller')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $invitation
            || $invitation->status !== 'pending'
            || $invitation->expires_at?->isPast()) {
            abort(410, 'Invitation is no longer valid');
        }

        if ($invitation->sub_reseller_id) {
            $child = Reseller::withoutGlobalScopes()->find($invitation->sub_reseller_id);

            if (! $child
                || in_array($child->review_status, ['rejected', 'terminated'], true)
                || $child->tenant()->first()?->status === 'terminated') {
                abort(410, 'Invitation is no longer valid');
            }
        }

        return $invitation;
    }

    private function findChild(string $publicId): Reseller
    {
        return Reseller::withoutGlobalScopes()
            ->where('public_id', $publicId)
            ->whereNotNull('parent_reseller_id')
            ->firstOrFail();
    }

    private function currentReseller(): ?Reseller
    {
        return Reseller::withoutGlobalScopes()
            ->where('tenant_id', app(TenantContext::class)->id())
            ->first();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveParent(array $validated): Reseller
    {
        $parent = $this->currentReseller();
        if ($parent) {
            return $parent;
        }

        abort_unless(app(TenantContext::class)->isPlatform(), 403);

        if (empty($validated['parent_public_id'])) {
            throw ValidationException::withMessages([
                'parent_public_id' => 'Select the parent reseller for this invitation.',
            ]);
        }

        return Reseller::withoutGlobalScopes()
            ->where('public_id', $validated['parent_public_id'])
            ->firstOrFail();
    }

    private function authorizeRelationship(Request $request, Reseller $child): void
    {
        abort_unless(
            $request->user()->can('resellers.create') || $request->user()->can('resellers.approve'),
            403,
        );

        if (app(TenantContext::class)->isPlatform()) {
            return;
        }

        $parent = $this->currentReseller();
        abort_unless($parent && $child->parent_reseller_id === $parent->id, 403);
    }
}
