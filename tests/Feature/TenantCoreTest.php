<?php

namespace Tests\Feature;

use App\Exceptions\TenantContextMissingException;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantSetting;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_tenant_and_domain_tables_exist(): void
    {
        $this->assertDatabaseHas('tenants', [
            'slug' => 'central',
            'type' => 'platform',
            'status' => 'active',
        ]);
    }

    public function test_no_context_tenant_owned_reads_return_no_rows(): void
    {
        app(TenantContext::class)->clear();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, TenantSetting::query()->count());
    }

    public function test_tenant_context_missing_id_is_fail_loud(): void
    {
        app(TenantContext::class)->clear();

        $this->expectException(TenantContextMissingException::class);
        app(TenantContext::class)->id();
    }

    public function test_reseller_subdomain_resolves_tenant_context(): void
    {
        $tenant = $this->createTenant('reseller-a');

        $response = $this->get('http://reseller-a.frenchies.com/login');

        $response->assertOk();
        $this->assertTrue(app(TenantContext::class)->is($tenant->id));
    }

    public function test_active_custom_domain_resolves_tenant_context(): void
    {
        $tenant = $this->createTenant('reseller-b');
        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => 'shop.example.test',
            'type' => 'custom',
            'status' => 'active',
        ]);

        $this->get('http://shop.example.test/login')->assertOk();
        $this->assertTrue(app(TenantContext::class)->is($tenant->id));
    }

    public function test_unknown_host_has_no_tenant_context(): void
    {
        app(TenantContext::class)->clear();

        $this->get('http://unknown.example.test/login')->assertOk();
        $this->assertNull(app(TenantContext::class)->get());
    }

    public function test_cross_tenant_setting_write_is_rejected(): void
    {
        $tenantA = $this->createTenant('tenant-a');
        $tenantB = $this->createTenant('tenant-b');

        app(TenantContext::class)->bind(TenantInfo::fromModel($tenantA));

        $this->expectException(TenantContextMissingException::class);
        TenantSetting::query()->create([
            'tenant_id' => $tenantB->id,
            'key' => 'invoice.format',
            'value' => ['format' => 'invalid'],
            'type' => 'json',
        ]);
    }

    public function test_tenant_model_writes_are_auto_scoped(): void
    {
        $tenant = $this->createTenant('tenant-auto');
        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));

        $setting = TenantSetting::query()->create([
            'key' => 'invoice.format',
            'value' => ['format' => 'FR-{SEQ}'],
            'type' => 'json',
        ]);

        $this->assertSame($tenant->id, $setting->tenant_id);
    }

    public function test_suspended_tenant_is_not_reachable(): void
    {
        $tenant = $this->createTenant('suspended-reseller');
        $tenant->update(['status' => 'suspended']);
        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => 'suspended.example.test',
            'type' => 'custom',
            'status' => 'active',
        ]);

        $this->get('http://suspended.example.test/login')->assertStatus(410);
    }

    public function test_api_token_host_mismatch_returns_forbidden(): void
    {
        $tenant = $this->createTenant('api-reseller');
        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));
        $token = $user->createToken('test', ['tenant:'.$tenant->id])->plainTextToken;

        $this->getJson('http://wrong.example.test/api/user', [
            'Authorization' => 'Bearer '.$token,
        ])->assertForbidden();
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
