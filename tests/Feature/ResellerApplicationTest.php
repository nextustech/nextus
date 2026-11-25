<?php

namespace Tests\Feature;

use App\Models\Reseller;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ResellerApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_application_creates_pending_tenant_and_standard_kyc(): void
    {
        Storage::fake('local');

        $response = $this->post('/resellers/register', [
            'name' => 'Acme Reseller',
            'legal_name' => 'Acme Reseller Private Limited',
            'slug' => 'acme-reseller',
            'email' => 'owner@acme.test',
            'phone' => '+919999999999',
            'pan' => 'ABCDE1234F',
            'gstin' => null,
            'gstin_unregistered_declaration' => true,
            'business_address' => '1 Market Road, Mumbai',
            'bank_proof' => UploadedFile::fake()->create('bank-proof.pdf', 100, 'application/pdf'),
            'aup_accepted' => true,
            'dpdp_accepted' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('tenants', [
            'slug' => 'acme-reseller',
            'status' => 'pending',
            'type' => 'reseller',
        ]);

        $this->assertDatabaseHas('resellers', [
            'name' => 'Acme Reseller',
            'email' => 'owner@acme.test',
            'kyc_status' => 'standard',
            'review_status' => 'pending',
        ]);
    }

    public function test_standard_kyc_requires_gstin_or_unregistered_declaration(): void
    {
        Storage::fake('local');

        $this->post('/resellers/register', [
            'name' => 'Incomplete Reseller',
            'slug' => 'incomplete-reseller',
            'email' => 'owner@incomplete.test',
            'phone' => '+919999999998',
            'pan' => 'ABCDE1234G',
            'gstin' => null,
            'gstin_unregistered_declaration' => false,
            'business_address' => '1 Road',
            'bank_proof' => UploadedFile::fake()->create('bank-proof.pdf', 100, 'application/pdf'),
            'aup_accepted' => true,
            'dpdp_accepted' => true,
        ])->assertSessionHasErrors('gstin');

        $this->assertDatabaseMissing('tenants', ['slug' => 'incomplete-reseller']);
    }

    public function test_platform_admin_can_request_info_or_approve_application(): void
    {
        Storage::fake('local');
        $central = Tenant::query()->where('slug', 'central')->firstOrFail();
        app(TenantContext::class)->bind(TenantInfo::fromModel($central));

        app(PermissionRegistrar::class)->setPermissionsTeamId($central->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('resellers.approve', 'web');
        $role = Role::findOrCreate('platform.admin', 'web');
        $role->givePermissionTo('resellers.approve');

        $admin = User::factory()->create(['tenant_id' => $central->id]);
        $admin->assignRole($role);

        $tenant = Tenant::query()->create([
            'public_id' => str_pad('TENANTAPP', 26, 'X'),
            'slug' => 'pending-reseller',
            'name' => 'Pending Reseller',
            'type' => 'reseller',
            'status' => 'pending',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en-IN',
        ]);

        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));
        $reseller = Reseller::query()->create([
            'public_id' => str_pad('RESELLERAPP', 26, 'X'),
            'tenant_id' => $tenant->id,
            'name' => 'Pending Reseller',
            'email' => 'pending@example.test',
            'phone' => '+919999999997',
            'pan' => 'ABCDE1234H',
            'business_address' => '1 Test Road',
            'bank_proof_path' => 'kyc/bank-proofs/test.pdf',
            'gstin_unregistered_declaration' => true,
            'email_verified' => true,
            'phone_verified' => true,
            'aup_accepted_at' => now(),
            'dpdp_accepted_at' => now(),
            'kyc_status' => 'standard',
            'review_status' => 'pending',
            'kyc_submitted_at' => now(),
            'review_sla_due_at' => now()->addHours(24),
            'effective_status' => 'active',
        ]);

        app(TenantContext::class)->bind(TenantInfo::fromModel($central));
        $this->actingAs($admin)
            ->post('/resellers/'.$reseller->public_id.'/approve', ['review_notes' => 'KYC verified'])
            ->assertRedirect();

        $this->assertSame('approved', $reseller->fresh()->review_status);
        $this->assertSame('active', $reseller->fresh()->tenant->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant.lifecycle.changed',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_tenant_suspension_resume_and_termination_are_audited(): void
    {
        $central = Tenant::query()->where('slug', 'central')->firstOrFail();
        app(TenantContext::class)->bind(TenantInfo::fromModel($central));
        app(PermissionRegistrar::class)->setPermissionsTeamId($central->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate('tenants.suspend', 'web');
        Permission::findOrCreate('tenants.resume', 'web');
        Permission::findOrCreate('tenants.delete', 'web');
        $role = Role::findOrCreate('platform.super_admin', 'web');
        $role->givePermissionTo(['tenants.suspend', 'tenants.resume', 'tenants.delete']);

        $admin = User::factory()->create(['tenant_id' => $central->id]);
        $admin->assignRole($role);

        $tenant = Tenant::query()->create([
            'public_id' => str_pad('LIFECYCLE1', 26, 'X'),
            'slug' => 'lifecycle-reseller',
            'name' => 'Lifecycle Reseller',
            'type' => 'reseller',
            'status' => 'active',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en-IN',
        ]);

        $this->actingAs($admin)
            ->post('/tenants/'.$tenant->public_id.'/suspend', [
                'reason' => 'Repeated policy violations require suspension.',
            ])
            ->assertRedirect();

        $this->assertSame('suspended', $tenant->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'tenant.suspended',
        ]);

        $this->actingAs($admin)
            ->post('/tenants/'.$tenant->public_id.'/resume')
            ->assertRedirect();

        $this->assertSame('active', $tenant->fresh()->status);

        $this->actingAs($admin)
            ->post('/tenants/'.$tenant->public_id.'/terminate', [
                'reason' => 'Tenancy is terminated after the review decision.',
            ])
            ->assertRedirect();

        $this->assertSame('terminated', $tenant->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'tenant.terminated',
        ]);
    }
}
