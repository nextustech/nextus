<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\WebsiteTheme;
use App\Services\ThemeCatalog;
use App\Services\ThemeCustomizationService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ThemeController extends Controller
{
    public function __construct(private readonly ThemeCustomizationService $themes) {}

    public function show(Request $request): InertiaResponse
    {
        abort_unless($request->user()->can('cms.view'), 403);

        $theme = $this->themeForCurrentTenant();

        return Inertia::render('Cms/Branding', [
            'theme' => $theme,
            'gallery' => ThemeCatalog::templates(),
            'fontPairs' => ThemeCatalog::fontPairs(),
            'radiusPresets' => ThemeCatalog::radiusPresets(),
            'shadowPresets' => ThemeCatalog::shadowPresets(),
            'liveTokens' => $this->themes->liveTokens($theme),
            'stagedTokens' => $this->themes->stagedTokens($theme),
            'hasStagedChanges' => $theme->hasStagedChanges(),
            'contrastWarnings' => $this->themes->contrastWarnings($this->themes->stagedTokens($theme)),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->can('cms.themes_manage'), 403);

        $validated = $request->validate([
            'tokens' => ['required', 'array'],
        ]);

        $theme = $this->themeForCurrentTenant();
        $this->themes->stage($theme, $validated['tokens'], $request);

        return back()->with('status', 'Changes staged. Publish to make them live.');
    }

    public function publish(Request $request)
    {
        abort_unless($request->user()->can('cms.themes_manage'), 403);

        $theme = $this->themeForCurrentTenant();
        $this->themes->publish($theme, $request);

        return back()->with('status', 'Theme published. Changes are now live.');
    }

    public function reset(Request $request)
    {
        abort_unless($request->user()->can('cms.themes_manage'), 403);

        $validated = $request->validate([
            'keys' => ['sometimes', 'array'],
            'keys.*' => ['string'],
        ]);

        $theme = $this->themeForCurrentTenant();
        $this->themes->resetTokens($theme, $validated['keys'] ?? [], $request);

        return back()->with('status', 'Theme reset to template defaults.');
    }

    public function switchTemplate(Request $request)
    {
        abort_unless($request->user()->can('cms.themes_manage'), 403);

        $validated = $request->validate([
            'template' => ['required', 'string', 'in:'.implode(',', array_keys(ThemeCatalog::templates()))],
        ]);

        $theme = $this->themeForCurrentTenant();
        $this->themes->switchTemplate($theme, $validated['template'], $request);

        return back()->with('status', 'Template staged. Publish to make it live.');
    }

    public function previewCss(Request $request): Response
    {
        abort_unless($request->user()->can('cms.view'), 403);

        $staged = $request->boolean('staged');
        $css = $this->themes->cssBlock($this->themeForCurrentTenant(), $staged);

        return response($css, 200, ['Content-Type' => 'text/css; charset=UTF-8']);
    }

    private function themeForCurrentTenant(): WebsiteTheme
    {
        $tenantId = app(TenantContext::class)->id();

        $theme = WebsiteTheme::query()->where('tenant_id', $tenantId)->first();
        if ($theme) {
            return $theme;
        }

        // Tenant has no theme row yet (created before provisioning ran).
        $tenant = Tenant::query()->findOrFail($tenantId);
        $defaults = ThemeCatalog::defaultsFor('saas_modern');

        return WebsiteTheme::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'SaaS Modern',
            'template' => 'saas_modern',
            'color_primary' => $defaults['color_primary'],
            'color_secondary' => $defaults['color_secondary'],
            'color_accent' => $defaults['color_accent'],
            'font_family' => 'system-ui',
            'font_pair' => $defaults['font_pair'],
            'radius_preset' => $defaults['radius_preset'],
            'shadow_preset' => $defaults['shadow_preset'],
            'published_config' => array_merge($defaults, [
                'logo_path' => null,
                'logo_dark_path' => null,
                'favicon_path' => null,
                'banner_image' => null,
                'banner_text' => null,
                'custom_css' => null,
            ]),
            'published_at' => now(),
        ]);
    }
}
