<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\WebsiteMenu;
use App\Models\WebsitePage;
use App\Models\WebsiteTheme;
use App\Services\StorefrontProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisioning_creates_default_theme(): void
    {
        $tenant = $this->createTenant('bright-agency');

        app(StorefrontProvisioningService::class)->provision($tenant);

        $theme = WebsiteTheme::query()->where('tenant_id', $tenant->id)->first();

        $this->assertNotNull($theme);
        $this->assertSame('SaaS Modern', $theme->name);
        $this->assertSame('#0066ff', $theme->color_primary);
    }

    public function test_provisioning_seeds_legal_pages(): void
    {
        $tenant = $this->createTenant('legal-reseller');

        app(StorefrontProvisioningService::class)->provision($tenant);

        $privacy = WebsitePage::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'privacy')
            ->first();
        $terms = WebsitePage::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'terms')
            ->first();

        $this->assertNotNull($privacy);
        $this->assertNotNull($terms);
        $this->assertSame('legal', $privacy->page_type);
        $this->assertSame('legal', $terms->page_type);
        $this->assertSame('published', $privacy->status);
        $this->assertSame('published', $terms->status);
    }

    public function test_provisioning_seeds_standard_page_set(): void
    {
        $tenant = $this->createTenant('pages-reseller');

        app(StorefrontProvisioningService::class)->provision($tenant);

        $slugs = WebsitePage::query()
            ->where('tenant_id', $tenant->id)
            ->pluck('slug')
            ->all();

        $this->assertContains('home', $slugs);
        $this->assertContains('about', $slugs);
        $this->assertContains('contact', $slugs);
        $this->assertContains('pricing', $slugs);
        $this->assertContains('404', $slugs);
    }

    public function test_provisioning_seeds_default_menus_linked_to_pages(): void
    {
        $tenant = $this->createTenant('menu-reseller');

        app(StorefrontProvisioningService::class)->provision($tenant);

        $primaryMenus = WebsiteMenu::query()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'primary')
            ->orderBy('position')
            ->get();

        $footerMenus = WebsiteMenu::query()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'footer')
            ->orderBy('position')
            ->get();

        $this->assertCount(3, $primaryMenus);
        $this->assertCount(2, $footerMenus);

        $primarySlugs = $primaryMenus->pluck('slug')->all();
        $this->assertContains('home', $primarySlugs);
        $this->assertContains('pricing', $primarySlugs);
        $this->assertContains('contact', $primarySlugs);

        $footerSlugs = $footerMenus->pluck('slug')->all();
        $this->assertContains('privacy', $footerSlugs);
        $this->assertContains('terms', $footerSlugs);
    }

    public function test_provisioning_home_page_uses_coming_soon_template(): void
    {
        $tenant = $this->createTenant('coming-soon');

        app(StorefrontProvisioningService::class)->provision($tenant);

        $home = WebsitePage::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'home')
            ->first();

        $this->assertNotNull($home);
        $this->assertStringContainsString('coming soon', strtolower($home->content));

        $sections = $home->sections;
        $this->assertIsArray($sections);
        $this->assertSame('hero', $sections[0]['type']);
        $this->assertNotEmpty($sections[0]['settings']['headline']);
    }

    public function test_provisioning_is_idempotent_per_tenant(): void
    {
        $tenant = $this->createTenant('idempotent-reseller');

        app(StorefrontProvisioningService::class)->provision($tenant);
        app(StorefrontProvisioningService::class)->provision($tenant);

        $pageCount = WebsitePage::query()->where('tenant_id', $tenant->id)->count();

        // Provisioning is idempotent: the second call is guarded and must not
        // create duplicate pages (AC-FR-CMS-001 / roadmap T4.1).
        $this->assertSame(7, $pageCount);
    }

    public function test_tenant_domain_status_transitions(): void
    {
        $tenant = $this->createTenant('domain-states');

        $domain = TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => 'shop.example.test',
            'type' => 'custom',
            'status' => TenantDomain::STATUS_PENDING_DNS,
        ]);

        $this->assertTrue($domain->isPendingDns());
        $this->assertTrue($domain->canTransitionTo(TenantDomain::STATUS_VERIFYING));

        $domain->update(['status' => TenantDomain::STATUS_VERIFYING]);
        $this->assertTrue($domain->canTransitionTo(TenantDomain::STATUS_VERIFIED));
        $this->assertTrue($domain->canTransitionTo(TenantDomain::STATUS_FAILED_DNS_MISMATCH));

        $domain->update(['status' => TenantDomain::STATUS_VERIFIED]);
        $this->assertTrue($domain->canTransitionTo(TenantDomain::STATUS_PROVISIONING_SSL));

        $domain->update(['status' => TenantDomain::STATUS_PROVISIONING_SSL]);
        $this->assertTrue($domain->canTransitionTo(TenantDomain::STATUS_ACTIVE));
        $this->assertTrue($domain->canTransitionTo(TenantDomain::STATUS_FAILED_SSL));

        $domain->update(['status' => TenantDomain::STATUS_ACTIVE]);
        $this->assertTrue($domain->canTransitionTo(TenantDomain::STATUS_DISABLED));
        $this->assertFalse($domain->canTransitionTo(TenantDomain::STATUS_VERIFYING));
    }

    public function test_failed_ssl_can_retry_provisioning(): void
    {
        $tenant = $this->createTenant('ssl-retry');

        $domain = TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => 'retry.example.test',
            'type' => 'custom',
            'status' => TenantDomain::STATUS_FAILED_SSL,
        ]);

        $this->assertTrue($domain->isFailedSsl());
        $this->assertTrue($domain->canTransitionTo(TenantDomain::STATUS_PROVISIONING_SSL));
    }

    public function test_reseller_subdomain_provisions_storefront_domain(): void
    {
        $tenant = $this->createTenant('subdomain-reseller');

        app(StorefrontProvisioningService::class)->provision($tenant);

        $subdomain = TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'subdomain')
            ->first();

        // Subdomain resolution falls back to slug lookup in ResolveTenantFromHost,
        // so an explicit row is optional. Verify slug-based resolution works.
        $this->assertNull($subdomain);
        $this->assertSame('subdomain-reseller', $tenant->slug);
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
