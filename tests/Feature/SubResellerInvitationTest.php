<?php

namespace Tests\Feature;

use App\Exceptions\SubResellerLimitReachedException;
use App\Models\AuditLog;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\SubResellerInvitationCreated;
use App\Services\ResellerHierarchyService;
use App\Services\SubResellerInvitationService;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SubResellerInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['resellers.auto_activate_sub_resellers' => false]);
    }

    public function test_parent_invite_creates_pending_child_with_parent_linkage_and_closure_rows(): void
    {
        Notification::fake();
        [$parentTenant, $parent, $actor] = $this->createParent();

        $response = $this->actingAs($actor)->post(
            $this->urlFor($parentTenant, '/resellers/sub-resellers'),
            $this->payload(),
        );

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Sub-reseller invited. Awaiting platform review.');

        $child = Reseller::withoutGlobalScopes()->where('name', 'Partner One')->firstOrFail();
        $this->assertSame('sub_reseller', $child->type);
        $this->assertSame($parent->id, $child->parent_reseller_id);
        $this->assertSame('minimal', $child->kyc_status);
        $this->assertSame('pending', $child->review_status);
        $this->assertSame('Ada Owner', $child->owner_name);
        $this->assertNull($child->pan);

        $this->assertDatabaseHas('tenants', [
            'id' => $child->tenant_id,
            'status' => 'pending',
            'type' => 'reseller',
        ]);
        $this->assertDatabaseHas('reseller_hierarchy', [
            'ancestor_reseller_id' => $parent->id,
            'descendant_reseller_id' => $child->id,
            'depth' => 1,
        ]);
        $this->assertDatabaseHas('reseller_hierarchy', [
            'ancestor_reseller_id' => $child->id,
            'descendant_reseller_id' => $child->id,
            'depth' => 0,
        ]);
        $this->assertSame(1, $parent->fresh()->child_count);

        $invitation = UserInvitation::withoutGlobalScopes()
            ->where('type', 'sub_reseller')
            ->where('sub_reseller_id', $child->id)
            ->firstOrFail();
        $this->assertSame($parentTenant->id, $invitation->tenant_id, 'Token lives in the inviter tenant');
        $this->assertSame('partner-one@example.test', $invitation->email);

        Notification::assertSentOnDemand(SubResellerInvitationCreated::class);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'sub_reseller.invited',
            'tenant_id' => $parentTenant->id,
        ]);
    }

    public function test_auto_activation_provisions_child_and_does_not_issue_a_second_owner_invitation(): void
    {
        config(['resellers.auto_activate_sub_resellers' => true]);
        Notification::fake();
        [$parentTenant, $parent, $actor] = $this->createParent();

        $this->actingAs($actor)
            ->post($this->urlFor($parentTenant, '/resellers/sub-resellers'), $this->payload())
            ->assertRedirect();

        $child = Reseller::withoutGlobalScopes()->where('name', 'Partner One')->firstOrFail();
        $this->assertNotNull($child->activated_at);
        $this->assertSame('approved', $child->review_status);
        $this->assertSame('active', $child->tenant->status);
        $this->assertSame(1, UserInvitation::withoutGlobalScopes()
            ->where('sub_reseller_id', $child->id)
            ->where('status', 'pending')
            ->count());

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $child->tenant_id,
            'host' => $child->tenant->slug.'.frenchies.com',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'sub_reseller.activated',
            'tenant_id' => $child->tenant_id,
        ]);
    }

    public function test_acceptance_creates_owner_only_in_child_tenant_with_scoped_role(): void
    {
        Notification::fake();
        [$parentTenant, $parent, $actor] = $this->createParent();

        $result = app(SubResellerInvitationService::class)->invite($parent, $actor, $this->payload());
        $child = $result['reseller'];

        $this->get('/sub-reseller-invitations/'.$result['token'].'/accept')->assertOk();

        $this->post('/sub-reseller-invitations/'.$result['token'].'/accept', [
            'name' => 'Ada Owner',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/sub-reseller-invitations/'.$result['token'].'/accepted');

        $owner = User::withoutGlobalScopes()
            ->where('email', 'partner-one@example.test')
            ->firstOrFail();

        $this->get('/sub-reseller-invitations/'.$result['token'].'/accepted')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('pendingReview', true));
        $this->assertSame($child->tenant_id, $owner->tenant_id);
        $this->assertSame(0, User::withoutGlobalScopes()
            ->where('email', 'partner-one@example.test')
            ->where('tenant_id', $parentTenant->id)
            ->count());

        app(PermissionRegistrar::class)->setPermissionsTeamId($child->tenant_id);
        $this->assertTrue($owner->fresh()->hasRole('reseller.owner'));
        $this->assertSame('accepted', UserInvitation::withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $result['token']))
            ->value('status'));

        $this->flushSession();

        $this->post('/sub-reseller-invitations/'.$result['token'].'/accept', [
            'name' => 'Ada Owner',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(410);
        $this->assertSame(1, User::withoutGlobalScopes()
            ->where('email', 'partner-one@example.test')
            ->count());
    }

    public function test_active_child_owner_is_logged_in_and_lands_on_the_child_dashboard(): void
    {
        Notification::fake();
        config(['resellers.auto_activate_sub_resellers' => true]);
        [$parentTenant, $parent, $actor] = $this->createParent();

        $result = app(SubResellerInvitationService::class)->invite($parent, $actor, $this->payload());
        $childDashboard = $this->urlFor($result['reseller']->tenant()->firstOrFail(), '/dashboard');

        $this->post('/sub-reseller-invitations/'.$result['token'].'/accept', [
            'name' => 'Ada Owner',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect($childDashboard);

        $owner = User::withoutGlobalScopes()
            ->where('email', 'partner-one@example.test')
            ->firstOrFail();
        $this->assertAuthenticatedAs($owner);
        $this->get($childDashboard)->assertOk();
    }

    public function test_rejecting_a_child_revokes_its_pending_invitation(): void
    {
        Notification::fake();
        [, $parent, $actor] = $this->createParent();
        $result = app(SubResellerInvitationService::class)->invite($parent, $actor, $this->payload());
        $child = $result['reseller'];
        $admin = $this->createPlatformAdmin(['resellers.view', 'resellers.approve']);

        $this->actingAs($admin)->post('/resellers/sub-resellers/'.$child->public_id.'/reject', [
            'reason' => 'Application did not meet the minimum business details requirement.',
        ])->assertRedirect();

        $this->assertSame('rejected', $child->fresh()->review_status);
        $this->assertSame('revoked', UserInvitation::withoutGlobalScopes()
            ->whereKey($result['invitation']->id)
            ->value('status'));
        $this->assertSame('terminated', $child->tenant()->firstOrFail()->status);

        $this->flushSession();
        $this->post('/sub-reseller-invitations/'.$result['token'].'/accept', [
            'name' => 'Ada Owner',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(410);
    }

    public function test_pending_invitation_email_is_scoped_to_the_inviting_tenant(): void
    {
        Notification::fake();
        [, $parentA, $actorA] = $this->createParent('parent-a', 'owner-a');
        [$tenantB, $parentB, $actorB] = $this->createParent('parent-b', 'owner-b');

        app(SubResellerInvitationService::class)->invite($parentA, $actorA, $this->payload());

        $this->actingAs($actorB)
            ->post($this->urlFor($tenantB, '/resellers/sub-resellers'), $this->payload([
                'business_name' => 'Partner Two',
                'owner_email' => 'partner-one@example.test',
            ]))
            ->assertRedirect();

        $this->assertSame(2, Reseller::withoutGlobalScopes()->where('type', 'sub_reseller')->count());
    }

    public function test_platform_admin_can_resend_an_invitation(): void
    {
        Notification::fake();
        [, $parent, $actor] = $this->createParent();
        $result = app(SubResellerInvitationService::class)->invite($parent, $actor, $this->payload());
        $admin = $this->createPlatformAdmin(['resellers.view', 'resellers.create']);

        $this->actingAs($admin)
            ->post('/resellers/sub-resellers/'.$result['reseller']->public_id.'/resend')
            ->assertRedirect();

        $this->assertSame('revoked', UserInvitation::withoutGlobalScopes()
            ->whereKey($result['invitation']->id)
            ->value('status'));
        $this->assertSame(1, UserInvitation::withoutGlobalScopes()
            ->where('sub_reseller_id', $result['reseller']->id)
            ->where('status', 'pending')
            ->count());
        Notification::assertSentOnDemand(SubResellerInvitationCreated::class);
    }

    public function test_reinviting_existing_owner_and_business_routes_to_existing_record(): void
    {
        Notification::fake();
        [$parentTenant, , $actor] = $this->createParent();

        $this->actingAs($actor)
            ->post($this->urlFor($parentTenant, '/resellers/sub-resellers'), $this->payload())
            ->assertRedirect();

        $child = Reseller::withoutGlobalScopes()->where('name', 'Partner One')->firstOrFail();

        $this->actingAs($actor)
            ->post($this->urlFor($parentTenant, '/resellers/sub-resellers'), $this->payload())
            ->assertRedirect(route('resellers.sub-resellers.index', ['existing' => $child->public_id]))
            ->assertSessionHas('status');

        $this->assertSame(1, Reseller::withoutGlobalScopes()->where('type', 'sub_reseller')->count());
    }

    public function test_non_platform_user_cannot_override_the_plan_limit(): void
    {
        [$parentTenant, , $actor] = $this->createParent();

        $this->actingAs($actor)
            ->post($this->urlFor($parentTenant, '/resellers/sub-resellers'), $this->payload([
                'override_limit' => true,
                'override_reason' => 'This request must be rejected because the actor is not platform.',
            ]))
            ->assertForbidden();

        $this->assertSame(0, Reseller::withoutGlobalScopes()->where('type', 'sub_reseller')->count());
    }

    public function test_approval_is_blocked_when_parent_exceeds_the_current_plan_limit(): void
    {
        [, $parent, $actor] = $this->createParent();
        $service = app(SubResellerInvitationService::class);
        $service->invite($parent, $actor, $this->payload(), overrideLimit: true);
        $child = $service->invite($parent, $actor, $this->payload([
            'business_name' => 'Partner Two',
            'owner_email' => 'partner-two@example.test',
        ]), overrideLimit: true)['reseller'];

        config(['resellers.default_max_sub_resellers' => 1]);
        $admin = $this->createPlatformAdmin(['resellers.view', 'resellers.approve']);

        $this->actingAs($admin)
            ->post('/resellers/sub-resellers/'.$child->public_id.'/approve')
            ->assertSessionHasErrors('sub_reseller');

        $this->assertSame('pending', $child->fresh()->review_status);
    }

    public function test_plan_limit_blocks_a_child_when_parent_is_at_capacity(): void
    {
        Notification::fake();
        config(['resellers.default_max_sub_resellers' => 1]);
        [$parentTenant, $parent, $actor] = $this->createParent();

        $this->actingAs($actor)
            ->post($this->urlFor($parentTenant, '/resellers/sub-resellers'), $this->payload())
            ->assertRedirect();
        $this->actingAs($actor)->post($this->urlFor($parentTenant, '/resellers/sub-resellers'), $this->payload([
            'business_name' => 'Partner Two',
            'owner_email' => 'partner-two@example.test',
        ]))->assertSessionHasErrors('sub_reseller');

        $this->assertSame(1, $parent->fresh()->child_count);
        $this->assertSame(0, Reseller::withoutGlobalScopes()->where('name', 'Partner Two')->count());
    }

    public function test_atomic_limit_guard_throws_before_writing_closure_rows(): void
    {
        [, $parent] = $this->createParent();
        $service = app(ResellerHierarchyService::class);
        $service->ensureSelfRow($parent);
        $child = $this->createChildReseller($parent, 'Limit child');
        $service->ensureSelfRow($child);
        $service->attachChild($parent, $child);

        config(['resellers.default_max_sub_resellers' => 1]);
        $second = $this->createChildReseller($parent, 'Second child');
        $service->ensureSelfRow($second);

        $this->expectException(SubResellerLimitReachedException::class);
        $service->attachChild($parent, $second, 1);
    }

    public function test_duplicate_owner_email_is_rejected(): void
    {
        Notification::fake();
        [$parentTenant, $parent, $actor] = $this->createParent();

        $this->actingAs($actor)
            ->post($this->urlFor($parentTenant, '/resellers/sub-resellers'), $this->payload())
            ->assertRedirect();
        $this->actingAs($actor)->post($this->urlFor($parentTenant, '/resellers/sub-resellers'), $this->payload([
            'business_name' => 'A Different Name',
        ]))->assertSessionHasErrors('owner_email');

        $this->assertSame(1, Reseller::withoutGlobalScopes()->where('type', 'sub_reseller')->count());
    }

    public function test_user_without_reseller_create_permission_cannot_invite(): void
    {
        [$parentTenant, , $actor] = $this->createParent();
        $actor->syncRoles([]);

        $this->actingAs($actor)
            ->post($this->urlFor($parentTenant, '/resellers/sub-resellers'), $this->payload())
            ->assertForbidden();
        $this->assertSame(0, Reseller::withoutGlobalScopes()->where('type', 'sub_reseller')->count());
    }

    public function test_resend_invalidates_the_previous_token(): void
    {
        Notification::fake();
        [$parentTenant, $parent, $actor] = $this->createParent();
        $result = app(SubResellerInvitationService::class)->invite($parent, $actor, $this->payload());
        $oldToken = $result['token'];

        $this->actingAs($actor)
            ->post($this->urlFor($parentTenant, '/resellers/sub-resellers/'.$result['reseller']->public_id.'/resend'))
            ->assertRedirect();

        $this->get('/sub-reseller-invitations/'.$oldToken.'/accept')->assertStatus(410);
        $this->assertSame(1, UserInvitation::withoutGlobalScopes()
            ->where('type', 'sub_reseller')
            ->where('status', 'pending')
            ->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'sub_reseller.invitation_resent']);
    }

    public function test_platform_can_approve_a_pending_child_and_audit_the_transition(): void
    {
        [$parentTenant, $parent, $actor] = $this->createParent();
        $result = app(SubResellerInvitationService::class)->invite($parent, $actor, $this->payload());
        $child = $result['reseller'];

        $central = Tenant::query()->where('slug', 'central')->firstOrFail();
        app(TenantContext::class)->bind(TenantInfo::fromModel($central));
        app(PermissionRegistrar::class)->setPermissionsTeamId($central->id);
        foreach (['resellers.approve', 'resellers.create'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $adminRole = Role::findOrCreate('platform.admin', 'web');
        $adminRole->syncPermissions(['resellers.approve', 'resellers.create']);
        $admin = User::factory()->create(['tenant_id' => $central->id]);
        $admin->assignRole('platform.admin');

        $this->actingAs($admin)
            ->post('/resellers/sub-resellers/'.$child->public_id.'/approve')
            ->assertRedirect();

        $this->assertSame('approved', $child->fresh()->review_status);
        $this->assertSame('active', $child->fresh()->tenant->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'sub_reseller.activated',
            'tenant_id' => $child->tenant_id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'sub_reseller.invited',
            'tenant_id' => $parentTenant->id,
        ]);
    }

    public function test_parent_cannot_approve_a_child_outside_its_subtree(): void
    {
        [$parentTenant, $parent, $actor] = $this->createParent();
        $result = app(SubResellerInvitationService::class)->invite($parent, $actor, $this->payload());

        [$otherTenant, $otherParent, $otherActor] = $this->createParent('other-parent', 'other-owner');
        $foreignChild = app(SubResellerInvitationService::class)
            ->invite($otherParent, $otherActor, $this->payload([
                'business_name' => 'Foreign Child',
                'owner_email' => 'foreign@example.test',
            ]))['reseller'];

        app(TenantContext::class)->bind(TenantInfo::fromModel($parent->tenant()->firstOrFail()));
        $this->actingAs($actor)
            ->post($this->urlFor($parentTenant, '/resellers/sub-resellers/'.$foreignChild->public_id.'/approve'))
            ->assertForbidden();

        $this->assertSame('pending', $foreignChild->fresh()->review_status);
    }

    public function test_sub_reseller_tree_screen_is_scoped_to_the_current_parent(): void
    {
        [$parentTenant, $parent, $actor] = $this->createParent();
        $child = app(SubResellerInvitationService::class)
            ->invite($parent, $actor, $this->payload())['reseller'];

        $this->actingAs($actor)->get($this->urlFor($parentTenant, '/resellers/sub-resellers'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Resellers/SubResellers')
                ->has('descendants', 1)
                ->where('descendants.0.public_id', $child->public_id));
    }

    public function test_pending_sub_reseller_appears_in_platform_onboarding_queue(): void
    {
        Notification::fake();
        [$parentTenant, $parent, $actor] = $this->createParent();
        $child = app(SubResellerInvitationService::class)
            ->invite($parent, $actor, $this->payload())['reseller'];
        $admin = $this->createPlatformAdmin(['resellers.view', 'resellers.approve']);

        $this->actingAs($admin)->get('/resellers')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Resellers/Index')
                ->where('applications.0.public_id', $child->public_id)
                ->where('applications.0.type', 'sub_reseller')
                ->where('applications.0.parent_name', $parentTenant->name)
                ->where('applications.0.review_status', 'pending'));
    }

    public function test_platform_admin_can_override_the_sub_reseller_limit_with_an_audited_reason(): void
    {
        Notification::fake();
        config(['resellers.default_max_sub_resellers' => 1]);
        [, $parent, $actor] = $this->createParent();

        $first = app(SubResellerInvitationService::class)->invite($parent, $actor, $this->payload());

        $admin = $this->createPlatformAdmin([
            'resellers.view', 'resellers.create', 'resellers.approve',
            'resellers.hierarchy_view', 'resellers.hierarchy_manage',
        ]);

        $this->actingAs($admin)->post('/resellers/sub-resellers', $this->payload([
            'business_name' => 'Override Child',
            'owner_email' => 'override@example.test',
        ] + [
            'parent_public_id' => $parent->public_id,
            'override_limit' => true,
            'override_reason' => 'Platform approved this exception after contract review.',
        ]))->assertRedirect();

        $overrideChild = Reseller::withoutGlobalScopes()->where('name', 'Override Child')->firstOrFail();
        $this->assertSame(2, $parent->fresh()->child_count);
        $this->assertNotSame($first['reseller']->id, $overrideChild->id);

        $audit = AuditLog::query()
            ->where('action', 'sub_reseller.invited')
            ->where('subject_id', $overrideChild->id)
            ->firstOrFail();
        $this->assertTrue($audit->metadata['limit_override']);
        $this->assertSame(
            'Platform approved this exception after contract review.',
            $audit->metadata['override_reason'],
        );
    }

    public function test_parent_cannot_mutate_a_child_tenant(): void
    {
        Notification::fake();
        [$parentTenant, $parent, $actor] = $this->createParent();
        $child = app(SubResellerInvitationService::class)
            ->invite($parent, $actor, $this->payload())['reseller'];

        // Parent roles only receive hierarchy/creation permissions; tenant
        // lifecycle permissions stay platform-scoped.
        app(PermissionRegistrar::class)->setPermissionsTeamId($parentTenant->id);
        $this->assertFalse($actor->can('tenants.suspend'));

        $this->actingAs($actor)
            ->post($this->urlFor($parentTenant, '/tenants/'.$child->tenant->public_id.'/suspend'), [
                'reason' => 'This suspension attempt must be blocked by permissions.',
            ])
            ->assertForbidden();

        $this->assertSame('pending', $child->tenant()->firstOrFail()->status);
    }

    private function urlFor(Tenant $tenant, string $path): string
    {
        return 'http://'.$tenant->slug.'.frenchies.com'.$path;
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Partner One',
            'owner_name' => 'Ada Owner',
            'owner_email' => 'partner-one@example.test',
            'city' => 'Pune',
            'gstin' => '',
            'note' => 'Onboarding via parent invite',
        ], $overrides);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function createPlatformAdmin(array $permissions): User
    {
        $central = Tenant::query()->where('slug', 'central')->firstOrFail();
        app(TenantContext::class)->bind(TenantInfo::fromModel($central));
        app(PermissionRegistrar::class)->setPermissionsTeamId($central->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate('platform.admin', 'web');
        $role->syncPermissions($permissions);

        $admin = User::factory()->create(['tenant_id' => $central->id]);
        $admin->assignRole($role);

        return $admin;
    }

    /**
     * @return array{0: Tenant, 1: Reseller, 2: User}
     */
    private function createParent(string $slug = 'parent-reseller', string $emailPrefix = 'owner'): array
    {
        $central = Tenant::query()->where('slug', 'central')->firstOrFail();
        app(TenantContext::class)->bind(TenantInfo::fromModel($central));
        app(PermissionRegistrar::class)->setPermissionsTeamId($central->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'resellers.view', 'resellers.create', 'resellers.approve',
            'resellers.hierarchy_view', 'resellers.hierarchy_manage',
        ];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $tenant = Tenant::query()->create([
            'public_id' => str_pad(strtoupper($slug), 26, 'X'),
            'slug' => $slug,
            'name' => ucfirst($slug),
            'type' => 'reseller',
            'status' => 'active',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en-IN',
        ]);
        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));

        $reseller = Reseller::query()->create([
            'public_id' => str_pad(strtoupper($slug.'-profile'), 26, 'X'),
            'tenant_id' => $tenant->id,
            'name' => ucfirst($slug),
            'email' => $emailPrefix.'@example.test',
            'phone' => '+919999999999',
            'pan' => 'ABCDE1234F',
            'business_address' => 'Test address',
            'bank_proof_path' => 'kyc/test.pdf',
            'gstin_unregistered_declaration' => true,
            'aup_accepted_at' => now(),
            'dpdp_accepted_at' => now(),
            'kyc_status' => 'standard',
            'review_status' => 'approved',
            'kyc_submitted_at' => now(),
            'review_sla_due_at' => now()->addHours(24),
            'effective_status' => 'active',
        ]);
        app(ResellerHierarchyService::class)->ensureSelfRow($reseller);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::findOrCreate('reseller.owner', 'web');
        $role->syncPermissions($permissions);

        $actor = User::factory()->create(['tenant_id' => $tenant->id]);
        $actor->assignRole('reseller.owner');

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));

        return [$tenant, $reseller, $actor];
    }

    private function createChildReseller(Reseller $parent, string $name): Reseller
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'slug' => \Illuminate\Support\Str::slug($name),
            'name' => $name,
            'type' => 'reseller',
            'status' => 'pending',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en-IN',
        ]);
        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));

        return Reseller::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'tenant_id' => $tenant->id,
            'parent_reseller_id' => $parent->id,
            'type' => 'sub_reseller',
            'name' => $name,
            'email' => strtolower(\Illuminate\Support\Str::slug($name)).'@example.test',
            'kyc_status' => 'minimal',
            'review_status' => 'pending',
            'effective_status' => 'pending',
        ]);
    }
}
