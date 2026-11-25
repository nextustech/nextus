<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\WebsiteMenu;
use App\Models\WebsitePage;
use App\Models\WebsiteTheme;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StorefrontProvisioningService
{
    public function provision(Tenant $tenant): void
    {
        // Idempotent: a tenant that already has a storefront is left untouched.
        if (WebsiteTheme::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        DB::transaction(function () use ($tenant) {
            $theme = $this->seedTheme($tenant);
            $workflow = app(PageWorkflowService::class);

            // 2. Seeded legal pages (privacy, terms) — page_type 'legal', required per FR-CMS-030.
            $this->seedPage($tenant, $theme, $workflow, [
                'system_key' => 'privacy',
                'title' => 'Privacy Policy',
                'slug' => 'privacy',
                'page_type' => 'legal',
                'meta_title' => 'Privacy Policy - '.$tenant->name,
                'meta_description' => 'Privacy Policy for '.$tenant->name,
                'html' => '<p>This is the privacy policy for '.htmlspecialchars($tenant->name).'.</p>',
            ]);

            $this->seedPage($tenant, $theme, $workflow, [
                'system_key' => 'terms',
                'title' => 'Terms of Service',
                'slug' => 'terms',
                'page_type' => 'legal',
                'meta_title' => 'Terms of Service - '.$tenant->name,
                'meta_description' => 'Terms of Service for '.$tenant->name,
                'html' => '<p>These are the terms of service for '.htmlspecialchars($tenant->name).'.</p>',
            ]);

            // 3. Standard page set (Home / Coming Soon, About, Contact, Pricing, 404).
            $this->seedPage($tenant, $theme, $workflow, [
                'title' => 'Home',
                'slug' => 'home',
                'page_type' => 'homepage',
                'meta_title' => $tenant->name.' - Coming Soon',
                'meta_description' => 'Coming soon to '.$tenant->name,
                'html' => '<p>Our website is coming soon. Stay tuned!</p>',
                'sections' => [
                    [
                        'id' => (string) Str::ulid(),
                        'type' => 'hero',
                        'settings' => [
                            'headline' => 'Welcome to '.$tenant->name,
                            'subheadline' => 'Our new website is launching soon.',
                        ],
                    ],
                ],
            ]);

            $this->seedPage($tenant, $theme, $workflow, [
                'title' => 'About Us',
                'slug' => 'about',
                'page_type' => 'about',
                'meta_title' => 'About Us - '.$tenant->name,
                'html' => '<p>Learn more about '.htmlspecialchars($tenant->name).'.</p>',
            ]);

            $this->seedPage($tenant, $theme, $workflow, [
                'title' => 'Contact',
                'slug' => 'contact',
                'page_type' => 'contact',
                'meta_title' => 'Contact Us - '.$tenant->name,
                'html' => '<p>Get in touch with '.htmlspecialchars($tenant->name).'.</p>',
            ]);

            $this->seedPage($tenant, $theme, $workflow, [
                'title' => 'Pricing',
                'slug' => 'pricing',
                'page_type' => 'pricing',
                'meta_title' => 'Pricing - '.$tenant->name,
                'html' => '<p>Explore our pricing plans.</p>',
            ]);

            $this->seedPage($tenant, $theme, $workflow, [
                'system_key' => 'not_found',
                'title' => 'Page Not Found',
                'slug' => '404',
                'page_type' => 'not_found',
                'meta_title' => 'Page Not Found - '.$tenant->name,
                'html' => '<p>The page you are looking for does not exist.</p>',
            ]);

            // 4. Default primary and footer menus linked to pages.
            $this->seedMenus($tenant);
        });
    }

    private function seedTheme(Tenant $tenant): WebsiteTheme
    {
        $defaults = ThemeCatalog::defaultsFor('saas_modern');
        $published = array_merge($defaults, [
            'logo_path' => null,
            'logo_dark_path' => null,
            'favicon_path' => null,
            'banner_image' => null,
            'banner_text' => null,
            'custom_css' => null,
        ]);

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
            'published_config' => $published,
            'published_at' => now(),
        ]);
    }

    /**
     * Seed a published page with its SEO block and first published revision.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function seedPage(Tenant $tenant, WebsiteTheme $theme, PageWorkflowService $workflow, array $attributes): WebsitePage
    {
        $html = (string) ($attributes['html'] ?? '');
        $sections = $attributes['sections'] ?? [
            [
                'id' => (string) Str::ulid(),
                'type' => 'custom_html',
                'settings' => ['html' => $html],
            ],
        ];

        $seo = [
            'meta_title' => $attributes['meta_title'] ?? $attributes['title'],
            'meta_description' => $attributes['meta_description'] ?? null,
            'og_image' => null,
            'canonical' => null,
            'robots' => null,
        ];

        $page = WebsitePage::query()->create([
            'tenant_id' => $tenant->id,
            'theme_id' => $theme->id,
            'system_key' => $attributes['system_key'] ?? null,
            'title' => $attributes['title'],
            'slug' => $attributes['slug'],
            'page_type' => $attributes['page_type'],
            'status' => 'published',
            'published_at' => now(),
            'meta_title' => $seo['meta_title'],
            'meta_description' => $seo['meta_description'],
            'content' => $html,
            'sections' => $sections,
            'seo' => $seo,
        ]);

        $revision = $workflow->snapshot($page, null);
        $page->update([
            'published_revision_id' => $revision->id,
            'published_slug' => $page->slug,
        ]);

        return $page;
    }

    private function seedMenus(Tenant $tenant): void
    {
        $items = [
            ['primary', 1, 'Home', 'home', '/home'],
            ['primary', 2, 'Pricing', 'pricing', '/pricing'],
            ['primary', 3, 'Contact', 'contact', '/contact'],
            ['footer', 1, 'Privacy Policy', 'privacy', '/privacy'],
            ['footer', 2, 'Terms of Service', 'terms', '/terms'],
        ];

        foreach ($items as [$name, $position, $displayName, $slug, $url]) {
            WebsiteMenu::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'slug' => $slug,
                'status' => 'published',
                'position' => $position,
                'display_name' => $displayName,
                'url' => $url,
            ]);
        }
    }
}
