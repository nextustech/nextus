<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationCreated;
use App\Services\AuditLogger;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserInvitationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('users.view'), 403);

        return Inertia::render('Users/Invitations', [
            'invitations' => UserInvitation::query()
                ->with('inviter:id,name')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(
            $request->user()->can('users.invite')
            && $request->user()->can('users.roles_assign'),
            403,
        );

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string'],
        ]);

        $email = strtolower($validated['email']);
        $tenantId = app(TenantContext::class)->id();

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        foreach ($validated['roles'] as $roleName) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('team_id', $tenantId)
                ->first();
            if (! $role) {
                throw ValidationException::withMessages([
                    'roles' => 'The selected role is not available in this tenant.',
                ]);
            }
            abort_unless($request->user()->can('users.roles_assign'), 403);
        }

        $plainToken = bin2hex(random_bytes(32));
        $invitation = UserInvitation::query()->create([
            'tenant_id' => $tenantId,
            'email' => $email,
            'roles' => array_values($validated['roles']),
            'department_grants' => null,
            'invited_by' => $request->user()->id,
            'token_hash' => hash('sha256', $plainToken),
            'status' => 'pending',
            'expires_at' => now()->addHours(168),
        ]);

        Notification::route('mail', $email)
            ->notify(new UserInvitationCreated($plainToken));
        app(AuditLogger::class)->record(
            'invitation.created',
            $invitation,
            ['email' => $email, 'roles' => array_values($validated['roles'])],
            $request,
        );

        return back()->with('status', 'Invitation created.');
    }

    public function revoke(Request $request, UserInvitation $invitation)
    {
        abort_unless($request->user()->can('users.invite'), 403);

        if ($invitation->status !== 'pending') {
            return back();
        }

        $invitation->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);
        app(AuditLogger::class)->record(
            'invitation.revoked',
            $invitation,
            ['before' => ['status' => 'pending'], 'after' => ['status' => 'revoked']],
            $request,
        );

        return back();
    }

    public function show(string $token): Response
    {
        $invitation = UserInvitation::withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $invitation
            || $invitation->status !== 'pending'
            || $invitation->expires_at->isPast()) {
            abort(410, 'Invitation is no longer valid');
        }

        return Inertia::render('Users/AcceptInvitation', [
            'email' => $invitation->email,
            'authenticated' => auth()->check(),
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $tokenHash = hash('sha256', $token);

        // This is an explicit public-token lookup: no tenant context is available
        // until the invitation's tenant has been validated.
        $invitation = UserInvitation::withoutGlobalScopes()
            ->where('token_hash', $tokenHash)
            ->first();

        if (! $invitation
            || $invitation->status !== 'pending'
            || $invitation->expires_at->isPast()) {
            abort(410, 'Invitation is no longer valid');
        }

        $tenant = $invitation->tenant()->firstOrFail();
        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        if ($request->user() && strtolower($request->user()->email) !== strtolower($invitation->email)) {
            abort(403, 'Invitation belongs to a different email');
        }

        $validated = $request->user()
            ? []
            : $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

        DB::transaction(function () use ($request, $invitation, $validated): void {
            $user = $request->user();

            if (! $user) {
                $user = User::query()->where('email', strtolower($invitation->email))
                    ->where('tenant_id', $invitation->tenant_id)
                    ->first();

                if (! $user) {
                    $user = User::query()->create([
                        'tenant_id' => $invitation->tenant_id,
                        'name' => $validated['name'] ?? 'Invited User',
                        'email' => strtolower($invitation->email),
                        'password' => $validated['password'],
                        'status' => 'active',
                    ]);
                }
            }

            $user->assignRole(...$invitation->roles);
            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);
            app(AuditLogger::class)->record(
                'invitation.accepted',
                $invitation,
                ['before' => ['status' => 'pending'], 'after' => ['status' => 'accepted']],
                $request,
            );
        });

        return redirect()->route('dashboard');
    }
}
