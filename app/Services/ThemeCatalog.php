<?php

namespace App\Services;

/**
 * Static catalog backing the starter gallery (FR-CMS-008), curated font pairs
 * (FR-CMS-011) and radius/shadow presets (FR-CMS-012).
 */
final class ThemeCatalog
{
    /**
     * The six named starter themes shipped at MVP (10-white-label-cms.md §3).
     *
     * @return array<string, array{name: string, description: string, defaults: array<string, mixed>}>
     */
    public static function templates(): array
    {
        return [
            'saas_modern' => [
                'name' => 'SaaS Modern',
                'description' => 'Card-heavy gradients, bold CTAs, product-led sections.',
                'defaults' => [
                    'color_primary' => '#0066ff',
                    'color_secondary' => '#666666',
                    'color_accent' => '#ff6600',
                    'font_pair' => 'inter',
                    'radius_preset' => 'md',
                    'shadow_preset' => 'elevated',
                ],
            ],
            'agency_bold' => [
                'name' => 'Agency Bold',
                'description' => 'Large type, dark mode default, portfolio-forward.',
                'defaults' => [
                    'color_primary' => '#111827',
                    'color_secondary' => '#6b7280',
                    'color_accent' => '#f59e0b',
                    'font_pair' => 'space_grotesk',
                    'radius_preset' => 'sm',
                    'shadow_preset' => 'subtle',
                ],
            ],
            'minimal_studio' => [
                'name' => 'Minimal Studio',
                'description' => 'Whitespace-first, serif/sans pairing, gallery emphasis.',
                'defaults' => [
                    'color_primary' => '#1f2937',
                    'color_secondary' => '#9ca3af',
                    'color_accent' => '#0d9488',
                    'font_pair' => 'playfair_inter',
                    'radius_preset' => 'none',
                    'shadow_preset' => 'none',
                ],
            ],
            'vertical_industry' => [
                'name' => 'Vertical Industry',
                'description' => 'Trust-signals-forward for POS/clinic/restaurant verticals.',
                'defaults' => [
                    'color_primary' => '#1d4ed8',
                    'color_secondary' => '#4b5563',
                    'color_accent' => '#059669',
                    'font_pair' => 'roboto_open',
                    'radius_preset' => 'sm',
                    'shadow_preset' => 'subtle',
                ],
            ],
            'commerce_pro' => [
                'name' => 'Commerce Pro',
                'description' => 'Catalog-dense, filter-friendly, promo strips.',
                'defaults' => [
                    'color_primary' => '#7c3aed',
                    'color_secondary' => '#52525b',
                    'color_accent' => '#e11d48',
                    'font_pair' => 'poppins',
                    'radius_preset' => 'lg',
                    'shadow_preset' => 'subtle',
                ],
            ],
            'startup_launch' => [
                'name' => 'Startup Launch',
                'description' => 'Compact hero + pricing table + FAQ focus.',
                'defaults' => [
                    'color_primary' => '#0f766e',
                    'color_secondary' => '#64748b',
                    'color_accent' => '#ea580c',
                    'font_pair' => 'montserrat',
                    'radius_preset' => 'md',
                    'shadow_preset' => 'subtle',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, heading: string, body: string, scale: float}>
     */
    public static function fontPairs(): array
    {
        return [
            'system' => ['label' => 'System', 'heading' => 'system-ui, sans-serif', 'body' => 'system-ui, sans-serif', 'scale' => 1.0],
            'inter' => ['label' => 'Inter', 'heading' => "'Inter', sans-serif", 'body' => "'Inter', sans-serif", 'scale' => 1.0],
            'poppins' => ['label' => 'Poppins', 'heading' => "'Poppins', sans-serif", 'body' => "'Poppins', sans-serif", 'scale' => 1.05],
            'montserrat' => ['label' => 'Montserrat', 'heading' => "'Montserrat', sans-serif", 'body' => "'Montserrat', sans-serif", 'scale' => 1.05],
            'roboto_open' => ['label' => 'Roboto + Open Sans', 'heading' => "'Roboto', sans-serif", 'body' => "'Open Sans', sans-serif", 'scale' => 1.0],
            'space_grotesk' => ['label' => 'Space Grotesk', 'heading' => "'Space Grotesk', sans-serif", 'body' => "'Inter', sans-serif", 'scale' => 1.1],
            'playfair_inter' => ['label' => 'Playfair + Inter', 'heading' => "'Playfair Display', serif", 'body' => "'Inter', sans-serif", 'scale' => 1.08],
            'lora_source' => ['label' => 'Lora + Source Sans', 'heading' => "'Lora', serif", 'body' => "'Source Sans 3', sans-serif", 'scale' => 1.02],
        ];
    }

    /** @return array<string, string> radius preset → px scale (6–12 px per 16 §2.10) */
    public static function radiusPresets(): array
    {
        return [
            'none' => '0px',
            'sm' => '6px',
            'md' => '8px',
            'lg' => '12px',
        ];
    }

    /** @return array<string, string> */
    public static function shadowPresets(): array
    {
        return [
            'none' => 'none',
            'subtle' => '0 1px 2px 0 rgb(0 0 0 / 0.05)',
            'elevated' => '0 4px 12px -2px rgb(0 0 0 / 0.15)',
        ];
    }

    public static function isTemplate(string $key): bool
    {
        return isset(self::templates()[$key]);
    }

    public static function isFontPair(string $key): bool
    {
        return isset(self::fontPairs()[$key]);
    }

    /** @return array<string, mixed> */
    public static function defaultsFor(string $template): array
    {
        return self::templates()[$template]['defaults'] ?? self::templates()['saas_modern']['defaults'];
    }

    public static function templateName(string $template): string
    {
        return self::templates()[$template]['name'] ?? 'SaaS Modern';
    }
}
