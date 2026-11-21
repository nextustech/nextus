<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_writer_records_attribution_and_redacts_secrets(): void
    {
        $central = Tenant::query()->where('slug', 'central')->firstOrFail();
        app(TenantContext::class)->bind(TenantInfo::fromModel($central));
        $user = User::factory()->create(['tenant_id' => $central->id]);

        $log = app(AuditLogger::class)->record(
            'auth.login.success',
            $user,
            [
                'email' => $user->email,
                'password' => 'do-not-store',
                'before' => ['token' => 'do-not-store'],
            ],
            $this->createMockRequest($user),
            actor: $user,
        );

        $this->assertSame('auth.login.success', $log->action);
        $this->assertSame($central->id, $log->tenant_id);
        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame('[REDACTED]', $log->metadata['password']);
        $this->assertSame('[REDACTED]', $log->metadata['before']['token']);
    }

    public function test_audit_rows_are_append_only(): void
    {
        $central = Tenant::query()->where('slug', 'central')->firstOrFail();
        app(TenantContext::class)->bind(TenantInfo::fromModel($central));
        $log = app(AuditLogger::class)->record('tenant.lifecycle.changed');

        $this->expectException(RuntimeException::class);
        $log->update(['action' => 'changed']);
    }

    public function test_ten_action_types_are_defined_for_phase_exit(): void
    {
        $required = [
            'auth.login.success',
            'auth.login.failed',
            'auth.logout',
            'password.reset.requested',
            'password.reset.completed',
            'tenant.lifecycle.changed',
            'invitation.created',
            'invitation.accepted',
            'invitation.revoked',
            'user.updated',
        ];

        foreach ($required as $action) {
            $this->assertContains($action, AuditLogger::ACTIONS);
        }
    }

    public function test_audit_viewer_requires_audit_permission_and_filters_tenant(): void
    {
        $central = Tenant::query()->where('slug', 'central')->firstOrFail();
        app(TenantContext::class)->bind(TenantInfo::fromModel($central));

        app(PermissionRegistrar::class)->setPermissionsTeamId($central->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::findOrCreate('platform.admin', 'web');
        $role->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('audit.view', 'web'));

        $user = User::factory()->create(['tenant_id' => $central->id]);
        $user->assignRole($role);

        app(AuditLogger::class)->record(
            'user.updated',
            $user,
            ['before' => ['name' => 'Before'], 'after' => ['name' => 'After']],
            $this->createMockRequest($user),
        );

        $this->actingAs($user)
            ->get('/audit-logs?action=user.updated')
            ->assertOk()
            ->assertInertia(
                fn (\Inertia\Testing\AssertableInertia $page) => $page
                    ->component('Audit/Index')
                    ->has('logs', 1)
                    ->where('logs.0.action', 'user.updated')
            );
    }

    private function createMockRequest(User $user): \Illuminate\Http\Request
    {
        $request = \Illuminate\Http\Request::create('/test', 'POST');
        $request->setUserResolver(fn () => $user);
        $request->headers->set('X-Request-ID', 'test-request-id');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        return $request;
    }
}
