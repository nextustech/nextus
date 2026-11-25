<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvitation;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_accept_creates_user_in_tenant_and_assigns_role(): void
    {
        Notification::fake();
        $tenant = $this->createTenant('invite-tenant');
        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        Role::findOrCreate('reseller.employee', 'web');

        $plainToken = bin2hex(random_bytes(32));
        $invitation = UserInvitation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'email' => 'invitee@example.test',
            'roles' => ['reseller.employee'],
            'token_hash' => hash('sha256', $plainToken),
            'status' => 'pending',
            'expires_at' => now()->addHours(168),
        ]);

        $this->get('/invitations/'.$plainToken.'/accept')->assertOk();

        $response = $this->post('/invitations/'.$plainToken.'/accept', [
            'name' => 'Invited User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'invitee@example.test')->firstOrFail();
        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertTrue($user->hasRole('reseller.employee'));
        $this->assertSame('accepted', $invitation->fresh()->status);
    }

    public function test_revoked_invitation_cannot_be_accepted(): void
    {
        $tenant = $this->createTenant('revoked-tenant');
        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));

        $plainToken = bin2hex(random_bytes(32));
        UserInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'email' => 'revoked@example.test',
            'roles' => ['reseller.employee'],
            'token_hash' => hash('sha256', $plainToken),
            'status' => 'revoked',
            'expires_at' => now()->addHours(168),
            'revoked_at' => now(),
        ]);

        $this->get('/invitations/'.$plainToken.'/accept')->assertStatus(410);
        $this->assertDatabaseMissing('users', ['email' => 'revoked@example.test']);
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $tenant = $this->createTenant('expired-tenant');
        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));

        $plainToken = bin2hex(random_bytes(32));
        UserInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'email' => 'expired@example.test',
            'roles' => ['reseller.employee'],
            'token_hash' => hash('sha256', $plainToken),
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        $this->post('/invitations/'.$plainToken.'/accept', [
            'name' => 'Expired User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(410);
    }

    public function test_invitation_cannot_cross_tenant_email_accounts(): void
    {
        $tenantA = $this->createTenant('tenant-a');
        $tenantB = $this->createTenant('tenant-b');

        app(TenantContext::class)->bind(TenantInfo::fromModel($tenantA));
        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'email' => 'same@example.test',
        ]);

        app(TenantContext::class)->bind(TenantInfo::fromModel($tenantB));
        $userB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'email' => 'same@example.test',
        ]);

        $this->assertNotSame($userA->id, $userB->id);
        $this->assertSame(2, User::withoutGlobalScopes()
            ->where('email', 'same@example.test')
            ->count());
    }

    private function createTenant(string $slug): Tenant
    {
        return Tenant::query()->create([
            'public_id' => str_pad(strtoupper(substr($slug, 0, 10)), 26, 'X'),
            'slug' => $slug,
            'name' => ucfirst($slug),
            'type' => 'reseller',
            'status' => 'active',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en-IN',
        ]);
    }
}
