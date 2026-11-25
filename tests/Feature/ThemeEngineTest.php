<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsitePage;
use App\Models\WebsiteTheme;
use App\Services\StorefrontProvisioningService;
use App\Services\ThemeCatalog;
use App\Services\ThemeCustomizationService;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ThemeEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_exposes_six_starter_templates(): void
    {
        $templates = ThemeCatalog::templates();

        $this->assertCount(6, $templates);
        $this->assertSame(
            ['saas_modern', 'agency_bold', 'minimal_studio', 'vertical_industry', 'commerce_pro', 'startup_launch'],
            array_keys($templates),
        );
        foreach ($templates as $template) {
            $this->assertNotEmpty($template['name']);
            $this->assertNotEmpty($template['description']);
            $this->assertArrayHasKey('color_primary', $template['defaults']);
        }
    }

    public function test_catalog_ships_eight_curated_font_pairs(): void
    {
        $this->assertCount(8, ThemeCatalog::fontPairs());
        $this->assertTrue(ThemeCatalog::isFontPair('playfair_inter'));
        $this->assertFalse(ThemeCatalog::isFontPair('comic_sans'));
    }

    public function test_provisioned_theme_publishes_initial_config(): void
    {
        $tenant = $this->createTenant('theme-provisioned');
        app(StorefrontProvisioningService::class)->provision($tenant);

        $theme = WebsiteTheme::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame('saas_modern', $theme->template);
        $this->assertNotNull($theme->published_at);
        $this->assertNotNull($theme->published_config);
        $this->assertSame('#0066ff', $theme->published_config['color_primary']);
        $this->assertFalse($theme->hasStagedChanges());
    }

    public function test_staged_changes_are_invisible_until_publish(): void
    {
        $theme = $this->makeTheme('stage-visitor');
        $service = app(ThemeCustomizationService::class);

        $service->stage($theme, ['color_accent' => '#123456']);

        // Editing session sees the staged accent...
        $stagedCss = $service->cssBlock($theme, staged: true);
        $this->assertStringContainsString('#123456', $stagedCss);
        $this->assertSame('#123456', $service->stagedTokens($theme)['color_accent']);

        // ...visitors keep seeing the previously published accent.
        $liveCss = $service->cssBlock($theme);
        $this->assertStringNotContainsString('#123456', $liveCss);
        $this->assertSame('#ff6600', $service->liveTokens($theme)['color_accent']);
        $this->assertTrue($theme->fresh()->hasStagedChanges());

        // Publish promotes the draft atomically.
        $service->publish($theme);

        $this->assertStringContainsString('#123456', $service->cssBlock($theme));
        $this->assertSame('#123456', $service->liveTokens($theme)['color_accent']);
        $this->assertFalse($theme->fresh()->hasStagedChanges());
    }

    public function test_publish_syncs_legacy_columns_and_invalidates_css_cache(): void
    {
        $theme = $this->makeTheme('publish-sync');
        $service = app(ThemeCustomizationService::class);

        $before = $service->cssBlock($theme);
        $this->assertStringContainsString('#ff6600', $before);

        $service->stage($theme, ['color_primary' => '#1d4ed8', 'color_accent' => '#059669']);
        $service->publish($theme);

        $after = $service->cssBlock($theme);
        $this->assertStringContainsString('#1d4ed8', $after);
        $this->assertStringContainsString('#059669', $after);

        $theme->refresh();
        $this->assertSame('#1d4ed8', $theme->color_primary);
        $this->assertSame('#059669', $theme->color_accent);
        $this->assertNotNull($theme->published_at);
    }

    public function test_per_token_reset_and_full_reset_are_audit_logged(): void
    {
        $theme = $this->makeTheme('reset-tokens');
        $service = app(ThemeCustomizationService::class);

        $service->stage($theme, [
            'color_primary' => '#111111',
            'banner_text' => 'Big sale',
            'custom_css' => '.promo { color: red; }',
        ]);
        $service->publish($theme);

        // Per-token reset reverts only that token to template defaults.
        $service->resetTokens($theme, ['color_primary']);

        $live = $service->liveTokens($theme);
        $this->assertSame('#0066ff', $live['color_primary']);
        $this->assertSame('Big sale', $live['banner_text']);

        // Full reset reverts every editable token to template defaults.
        $service->resetTokens($theme, []);

        $live = $service->liveTokens($theme);
        $this->assertSame('#0066ff', $live['color_primary']);
        $this->assertSame('#ff6600', $live['color_accent']);
        $this->assertNull($live['custom_css']);
        $this->assertNull($live['banner_text']);
        $this->assertFalse($theme->fresh()->hasStagedChanges());

        $this->assertSame(2, AuditLog::query()->where('action', 'theme.reset')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'theme.staged')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'theme.published')->count());
    }

    public function test_contrast_guard_darkens_failing_brand_color(): void
    {
        $theme = $this->makeTheme('contrast-guard');
        $service = app(ThemeCustomizationService::class);

        // Yellow fails 4.5:1 against white text.
        $service->stage($theme, ['color_primary' => '#ffcc00']);
        $service->publish($theme);

        $warnings = $service->contrastWarnings($service->liveTokens($theme));
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('4.5:1', $warnings[0]);

        $css = $service->cssBlock($theme);
        $this->assertStringContainsString('--sf-brand-500: #ffcc00', $css);

        // brand-600 is auto-darkened until it passes against white.
        $this->assertMatchesRegularExpression('/--sf-brand-600:\s*#[0-9a-f]{6}/', $css);
        preg_match('/--sf-brand-600:\s*(#[0-9a-f]{6})/', $css, $m);
        $brand600 = $m[1];
        $this->assertNotSame('#ffcc00', $brand600);
        $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio($brand600, '#ffffff'));
    }

    public function test_font_pair_selection_is_validated_against_catalog(): void
    {
        $theme = $this->makeTheme('font-pairs');
        $service = app(ThemeCustomizationService::class);

        $service->stage($theme, ['font_pair' => 'playfair_inter']);
        $service->publish($theme);

        $css = $service->cssBlock($theme);
        $this->assertStringContainsString("'Playfair Display', serif", $css);
        $this->assertStringContainsString('--sf-font-scale: 1.08', $css);

        $this->expectException(ValidationException::class);
        $service->stage($theme, ['font_pair' => 'not_a_pair']);
    }

    public function test_radius_and_shadow_presets_render_css_variables(): void
    {
        $theme = $this->makeTheme('presets');
        $service = app(ThemeCustomizationService::class);

        $service->stage($theme, ['radius_preset' => 'lg', 'shadow_preset' => 'elevated']);
        $service->publish($theme);

        $css = $service->cssBlock($theme);
        $this->assertStringContainsString('--sf-radius: 12px', $css);
        $this->assertStringContainsString('--sf-shadow: 0 4px 12px -2px', $css);
    }

    public function test_css_block_contains_white_label_variables_and_isolates_tenants(): void
    {
        $tenantA = $this->createTenant('brand-a');
        $tenantB = $this->createTenant('brand-b');
        app(StorefrontProvisioningService::class)->provision($tenantA);
        app(StorefrontProvisioningService::class)->provision($tenantB);

        $themeA = WebsiteTheme::query()->where('tenant_id', $tenantA->id)->firstOrFail();
        $themeB = WebsiteTheme::query()->where('tenant_id', $tenantB->id)->firstOrFail();

        $service = app(ThemeCustomizationService::class);
        $cssA = $service->cssBlock($themeA);

        foreach ([
            '--sf-brand-500', '--sf-brand-600', '--sf-brand-700', '--sf-accent',
            '--sf-logo-url', '--sf-favicon-url', '--sf-font-heading',
            '--sf-radius', '--sf-banner',
        ] as $variable) {
            $this->assertStringContainsString($variable, $cssA);
        }

        // Tenant A publishes a new accent; tenant B's block must not change.
        $service->stage($themeA, ['color_accent' => '#aa00aa']);
        $service->publish($themeA);

        $this->assertStringContainsString('#aa00aa', $service->cssBlock($themeA));
        $cssB = $service->cssBlock($themeB);
        $this->assertStringNotContainsString('#aa00aa', $cssB);
        $this->assertStringContainsString('#ff6600', $cssB);
    }

    public function test_switch_template_stages_defaults_and_preserves_pages(): void
    {
        $tenant = $this->createTenant('switch-pages');
        app(StorefrontProvisioningService::class)->provision($tenant);
        $theme = WebsiteTheme::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $pagesBefore = WebsitePage::query()->where('tenant_id', $tenant->id)->count();

        $service = app(ThemeCustomizationService::class);
        $service->switchTemplate($theme, 'agency_bold');

        $theme->refresh();
        $this->assertSame('agency_bold', $theme->template);
        $this->assertSame('Agency Bold', $theme->name);
        $this->assertSame($pagesBefore, WebsitePage::query()->where('tenant_id', $tenant->id)->count());

        // New template defaults are staged; the visitor-facing live theme is unchanged.
        $this->assertSame('#111827', $service->stagedTokens($theme)['color_primary']);
        $this->assertSame('#0066ff', $service->liveTokens($theme)['color_primary']);

        $this->assertSame(1, AuditLog::query()->where('action', 'theme.template_changed')->count());

        $this->expectException(\InvalidArgumentException::class);
        $service->switchTemplate($theme, 'no_such_template');
    }

    public function test_custom_css_rejects_import_expression_and_external_urls(): void
    {
        $theme = $this->makeTheme('css-policy');
        $service = app(ThemeCustomizationService::class);

        try {
            $service->stage($theme, ['custom_css' => "@import url('https://evil.example/x.css');"]);
            $this->fail('Expected @import to be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            $service->stage($theme, ['custom_css' => 'body { width: expression(alert(1)); }']);
            $this->fail('Expected expression() to be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            $service->stage($theme, ['custom_css' => 'body { background: url("https://tracker.example/pixel.png"); }']);
            $this->fail('Expected external url() to be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $service->stage($theme, ['custom_css' => '.promo { color: red; }']);
        $this->assertStringContainsString('.promo { color: red; }', $service->cssBlock($theme, staged: true));
    }

    public function test_branding_page_requires_view_permission(): void
    {
        $user = $this->makeUserWithPermissions([]);

        $this->actingAs($user)->get('/branding')->assertForbidden();
    }

    public function test_branding_page_renders_for_users_with_cms_view(): void
    {
        $user = $this->makeUserWithPermissions(['cms.view']);

        $this->actingAs($user)
            ->get('/branding')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Cms/Branding')
                ->where('theme.template', 'saas_modern')
                ->has('gallery', 6)
                ->has('fontPairs', 8)
                ->where('hasStagedChanges', false));
    }

    public function test_staging_and_publish_endpoints_work_end_to_end(): void
    {
        $user = $this->makeUserWithPermissions(['cms.view', 'cms.themes_manage']);

        $this->actingAs($user)
            ->put('/branding', ['tokens' => ['color_accent' => '#123456']])
            ->assertRedirect();

        $theme = WebsiteTheme::query()->where('tenant_id', $this->centralTenant()->id)->firstOrFail();
        $this->assertTrue($theme->hasStagedChanges());
        $this->assertSame('#123456', $theme->draft_config['color_accent']);
        $this->assertSame('#ff6600', $theme->fresh()->color_accent);

        $this->actingAs($user)->post('/branding/publish')->assertRedirect();

        $theme->refresh();
        $this->assertFalse($theme->hasStagedChanges());
        $this->assertSame('#123456', $theme->color_accent);
    }

    public function test_update_endpoint_rejects_invalid_font_pair(): void
    {
        $user = $this->makeUserWithPermissions(['cms.view', 'cms.themes_manage']);

        $this->actingAs($user)
            ->put('/branding', ['tokens' => ['font_pair' => 'not_a_pair']])
            ->assertSessionHasErrors('font_pair');
    }

    public function test_update_endpoint_requires_theme_manage_permission(): void
    {
        $viewer = $this->makeUserWithPermissions(['cms.view']);

        $this->actingAs($viewer)
            ->put('/branding', ['tokens' => ['color_accent' => '#123456']])
            ->assertForbidden();
    }

    public function test_preview_css_endpoint_serves_staged_stylesheet(): void
    {
        $user = $this->makeUserWithPermissions(['cms.view', 'cms.themes_manage']);

        $this->actingAs($user)
            ->put('/branding', ['tokens' => ['color_accent' => '#123456']])
            ->assertRedirect();

        $response = $this->actingAs($user)->get('/branding/preview-css?staged=1');
        $response->assertOk();
        $this->assertStringContainsString('text/css', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('#123456', $response->getContent());

        $live = $this->actingAs($user)->get('/branding/preview-css');
        $this->assertStringNotContainsString('#123456', $live->getContent());
    }

    private function makeTheme(string $slug): WebsiteTheme
    {
        $tenant = $this->createTenant($slug);
        app(StorefrontProvisioningService::class)->provision($tenant);

        return WebsiteTheme::query()->where('tenant_id', $tenant->id)->firstOrFail();
    }

    private function createTenant(string $slug): Tenant
    {
        return Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'slug' => $slug,
            'name' => ucfirst($slug),
            'type' => 'reseller',
            'status' => 'active',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en-IN',
        ]);
    }

    private function centralTenant(): Tenant
    {
        return Tenant::query()->where('slug', 'central')->firstOrFail();
    }

    /** @param  list<string>  $permissions */
    private function makeUserWithPermissions(array $permissions): User
    {
        $central = $this->centralTenant();
        app(TenantContext::class)->bind(TenantInfo::fromModel($central));
        app(PermissionRegistrar::class)->setPermissionsTeamId($central->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roleName = 'theme.engine.test.'.($permissions[0] ?? 'none');
        $role = Role::findOrCreate($roleName, 'web');
        $role->syncPermissions($permissions);

        $user = User::factory()->create(['tenant_id' => $central->id]);
        $user->assignRole($role);

        return $user;
    }

    private function contrastRatio(string $a, string $b): float
    {
        $luminance = function (string $hex): float {
            $hex = ltrim($hex, '#');
            $r = hexdec(substr($hex, 0, 2)) / 255;
            $g = hexdec(substr($hex, 2, 2)) / 255;
            $b = hexdec(substr($hex, 4, 2)) / 255;

            $f = fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

            return 0.2126 * $f($r) + 0.7152 * $f($g) + 0.0722 * $f($b);
        };

        $la = $luminance($a);
        $lb = $luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }
}
