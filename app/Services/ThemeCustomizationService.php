<?php

namespace App\Services;

use App\Models\WebsiteTheme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ThemeCustomizationService
{
    private const CUSTOM_CSS_MAX_BYTES = 51200; // 50 KB cap, FR-CMS-051

    /** Token keys editable through the customizer (FR-CMS-010/011/012). */
    private const EDITABLE_TOKENS = [
        'logo_path',
        'logo_dark_path',
        'favicon_path',
        'color_primary',
        'color_secondary',
        'color_accent',
        'font_pair',
        'radius_preset',
        'shadow_preset',
        'banner_image',
        'banner_text',
        'custom_css',
    ];

    /**
     * Stage customization tokens as an unsaved draft (FR-CMS-013).
     *
     * @param  array<string, mixed>  $tokens
     *
     * @throws ValidationException
     */
    public function stage(WebsiteTheme $theme, array $tokens, ?Request $request = null): WebsiteTheme
    {
        $tokens = $this->validateTokens($tokens);

        $draft = array_merge($theme->draft_config ?? $this->liveTokens($theme), $tokens);
        $theme->update(['draft_config' => $draft]);

        app(AuditLogger::class)->record(
            'theme.staged',
            $theme,
            ['keys' => array_keys($tokens)],
            $request,
            $theme->tenant_id,
        );

        return $theme->refresh();
    }

    /**
     * Atomically promote the staged draft to live (FR-CMS-013, FR-CMS-015).
     */
    public function publish(WebsiteTheme $theme, ?Request $request = null): WebsiteTheme
    {
        $tokens = array_merge($this->liveTokens($theme), $theme->draft_config ?? []);

        return DB::transaction(function () use ($theme, $tokens, $request): WebsiteTheme {
            $previousCssKey = $this->cssCacheKey($theme);

            $theme->update([
                'published_config' => $tokens,
                'draft_config' => null,
                'published_at' => now(),
                // Legacy column mirrors keep older readers working.
                'color_primary' => $tokens['color_primary'],
                'color_secondary' => $tokens['color_secondary'],
                'color_accent' => $tokens['color_accent'],
                'font_pair' => $tokens['font_pair'],
                'radius_preset' => $tokens['radius_preset'],
                'shadow_preset' => $tokens['shadow_preset'],
                'logo_path' => $tokens['logo_path'],
                'logo_dark_path' => $tokens['logo_dark_path'],
                'favicon_path' => $tokens['favicon_path'],
                'banner_image' => $tokens['banner_image'],
                'banner_text' => $tokens['banner_text'],
                'custom_css' => $tokens['custom_css'],
            ]);

            Cache::forget($previousCssKey);

            app(AuditLogger::class)->record(
                'theme.published',
                $theme,
                ['published_at' => $theme->published_at?->toIso8601String()],
                $request,
                $theme->tenant_id,
            );

            return $theme->refresh();
        });
    }

    /**
     * Reset the given tokens (or every token when $keys is empty) to the
     * template defaults, audit-logged (FR-CMS-014).
     *
     * @param  list<string>  $keys
     */
    public function resetTokens(WebsiteTheme $theme, array $keys = [], ?Request $request = null): WebsiteTheme
    {
        $defaults = ThemeCatalog::defaultsFor($theme->template);
        $live = $this->liveTokens($theme);

        if ($keys === []) {
            // Full reset: staged draft cleared, live tokens revert to template defaults.
            $live = $defaults;
            $keys = self::EDITABLE_TOKENS;
        } else {
            $unknown = array_diff($keys, self::EDITABLE_TOKENS);
            if ($unknown !== []) {
                throw new InvalidArgumentException('Unknown theme tokens: '.implode(', ', $unknown));
            }
            foreach ($keys as $key) {
                $live[$key] = $defaults[$key] ?? null;
            }
        }

        return DB::transaction(function () use ($theme, $live, $keys, $request): WebsiteTheme {
            $theme->update([
                'published_config' => $live,
                'draft_config' => null,
                'published_at' => now(),
                'color_primary' => $live['color_primary'],
                'color_secondary' => $live['color_secondary'],
                'color_accent' => $live['color_accent'],
                'font_pair' => $live['font_pair'],
                'radius_preset' => $live['radius_preset'],
                'shadow_preset' => $live['shadow_preset'],
                'logo_path' => $live['logo_path'] ?? null,
                'logo_dark_path' => $live['logo_dark_path'] ?? null,
                'favicon_path' => $live['favicon_path'] ?? null,
                'banner_image' => $live['banner_image'] ?? null,
                'banner_text' => $live['banner_text'] ?? null,
                'custom_css' => $live['custom_css'] ?? null,
            ]);

            Cache::forget($this->cssCacheKey($theme));

            app(AuditLogger::class)->record(
                'theme.reset',
                $theme,
                ['keys' => $keys, 'full' => $keys === self::EDITABLE_TOKENS],
                $request,
                $theme->tenant_id,
            );

            return $theme->refresh();
        });
    }

    /**
     * Switch the starter template, staging the new template's default tokens.
     * Page content is untouched (FR-CMS-009).
     */
    public function switchTemplate(WebsiteTheme $theme, string $template, ?Request $request = null): WebsiteTheme
    {
        if (! ThemeCatalog::isTemplate($template)) {
            throw new InvalidArgumentException('Unknown theme template: '.$template);
        }

        $theme->update([
            'template' => $template,
            'name' => ThemeCatalog::templateName($template),
            'draft_config' => array_merge($this->liveTokens($theme), ThemeCatalog::defaultsFor($template)),
        ]);

        app(AuditLogger::class)->record(
            'theme.template_changed',
            $theme,
            ['template' => $template],
            $request,
            $theme->tenant_id,
        );

        return $theme->refresh();
    }

    /** Live visitor-facing tokens: template defaults < published config. */
    public function liveTokens(WebsiteTheme $theme): array
    {
        return array_merge(
            ThemeCatalog::defaultsFor($theme->template),
            $this->legacyColumnTokens($theme),
            $theme->published_config ?? [],
        );
    }

    /** Editing-session tokens: live + staged draft overrides (FR-CMS-013). */
    public function stagedTokens(WebsiteTheme $theme): array
    {
        if (! $theme->hasStagedChanges()) {
            return $this->liveTokens($theme);
        }

        return array_merge($this->liveTokens($theme), $theme->draft_config);
    }

    /**
     * Contrast warnings for the editor (16 §2.10): brand color must hold
     * 4.5:1 against white text, otherwise the engine darkens --sf-brand-600.
     *
     * @return list<string>
     */
    public function contrastWarnings(array $tokens): array
    {
        $warnings = [];

        foreach (['color_primary' => 'brand', 'color_accent' => 'accent'] as $key => $label) {
            $hex = (string) ($tokens[$key] ?? '');
            if ($hex === '' || $this->contrastRatio($hex, '#ffffff') >= 4.5) {
                continue;
            }

            $warnings[] = sprintf(
                '%s color %s fails 4.5:1 contrast with white text; a darkened variant is applied to --sf-brand-600 automatically.',
                $label,
                $hex,
            );
        }

        return $warnings;
    }

    /**
     * Render the cached CSS custom-property block for a theme
     * (16 §2.10 resolution chain, 15-minute cache).
     */
    public function cssBlock(WebsiteTheme $theme, bool $staged = false): string
    {
        $tokens = $staged ? $this->stagedTokens($theme) : $this->liveTokens($theme);
        $key = $this->cssCacheKey($theme, $staged, $tokens);

        return Cache::remember($key, now()->addMinutes(15), function () use ($tokens): string {
            return $this->renderCssBlock($tokens);
        });
    }

    /** @param  array<string, mixed>  $tokens */
    private function renderCssBlock(array $tokens): string
    {
        $primary = (string) ($tokens['color_primary'] ?? '#0066ff');
        $brand600 = $this->ensureContrast($primary, 4.5);
        $brand700 = $this->ensureContrast($brand600, 4.5);

        $fontPair = ThemeCatalog::fontPairs()[(string) ($tokens['font_pair'] ?? 'system')]
            ?? ThemeCatalog::fontPairs()['system'];
        $radius = ThemeCatalog::radiusPresets()[(string) ($tokens['radius_preset'] ?? 'md')] ?? '8px';
        $shadow = ThemeCatalog::shadowPresets()[(string) ($tokens['shadow_preset'] ?? 'subtle')] ?? 'none';

        $lines = [
            ':root {',
            '  --sf-brand-500: '.$primary.';',
            '  --sf-brand-600: '.$brand600.';',
            '  --sf-brand-700: '.$brand700.';',
            '  --sf-accent: '.($tokens['color_accent'] ?? '#ff6600').';',
            '  --sf-logo-url: '.($this->cssUrl($tokens['logo_path'] ?? null) ?? 'none').';',
            '  --sf-favicon-url: '.($this->cssUrl($tokens['favicon_path'] ?? null) ?? 'none').';',
            '  --sf-font-heading: '.$fontPair['heading'].';',
            '  --sf-font-body: '.$fontPair['body'].';',
            '  --sf-font-scale: '.$fontPair['scale'].';',
            '  --sf-radius: '.$radius.';',
            '  --sf-shadow: '.$shadow.';',
            '  --sf-banner: '.($this->cssUrl($tokens['banner_image'] ?? null) ?? 'none').';',
            '  --sf-banner-text: '.($this->cssString((string) ($tokens['banner_text'] ?? ''))).';',
            '}',
        ];

        $customCss = trim((string) ($tokens['custom_css'] ?? ''));
        if ($customCss !== '') {
            $lines[] = $customCss;
        }

        return implode("\n", $lines)."\n";
    }

    private function cssUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return 'url('.str_replace(['(', ')', "'", '"'], '', $path).')';
    }

    private function cssString(string $value): string
    {
        return '"'.str_replace(['"', "\n", "\r"], ['', ' ', ''], $value).'"';
    }

    /**
     * Darken $hex until it reaches 4.5:1 against white (16 §2.10 contrast guard).
     */
    private function ensureContrast(string $hex, float $minRatio): string
    {
        if ($this->contrastRatio($hex, '#ffffff') >= $minRatio) {
            return $hex;
        }

        [$r, $g, $b] = $this->hexToRgb($hex);
        for ($step = 0; $step < 100; $step++) {
            $r = (int) max(0, floor($r * 0.92));
            $g = (int) max(0, floor($g * 0.92));
            $b = (int) max(0, floor($b * 0.92));
            $candidate = sprintf('#%02x%02x%02x', $r, $g, $b);

            if ($this->contrastRatio($candidate, '#ffffff') >= $minRatio) {
                return $candidate;
            }
        }

        return '#000000';
    }

    /** WCAG 2.x contrast ratio between two hex colors. */
    private function contrastRatio(string $a, string $b): float
    {
        $la = $this->relativeLuminance($a);
        $lb = $this->relativeLuminance($b);

        $light = max($la, $lb);
        $dark = min($la, $lb);

        return ($light + 0.05) / ($dark + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = $this->hexToRgb($hex);

        $channels = [$r / 255, $g / 255, $b / 255];
        $channels = array_map(
            fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return [0, 0, 0];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Validate staged tokens against the catalog and injection policies.
     *
     * @param  array<string, mixed>  $tokens
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validateTokens(array $tokens): array
    {
        $tokens = array_intersect_key($tokens, array_flip(self::EDITABLE_TOKENS));

        $rules = [];
        foreach (array_keys($tokens) as $key) {
            $rules[$key] = match ($key) {
                'color_primary', 'color_secondary', 'color_accent' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'font_pair' => ['required', 'string', 'in:'.implode(',', array_keys(ThemeCatalog::fontPairs()))],
                'radius_preset' => ['required', 'string', 'in:'.implode(',', array_keys(ThemeCatalog::radiusPresets()))],
                'shadow_preset' => ['required', 'string', 'in:'.implode(',', array_keys(ThemeCatalog::shadowPresets()))],
                'banner_text' => ['nullable', 'string', 'max:200'],
                'custom_css' => ['nullable', 'string', 'max:'.self::CUSTOM_CSS_MAX_BYTES],
                default => ['nullable', 'string', 'max:191'],
            };
        }

        if ($tokens !== []) {
            validator($tokens, $rules)->validate();
        }

        if (isset($tokens['custom_css'])) {
            $this->assertCustomCssAllowed((string) $tokens['custom_css']);
        }

        return $tokens;
    }

    /** FR-CMS-051: forbid @import, expression() and external url() lookups. */
    private function assertCustomCssAllowed(string $css): void
    {
        $css = preg_replace('!/\\*.*?\\*/!s', '', $css) ?? $css;
        $patterns = [
            '/@import/i' => '@import is not allowed in custom CSS.',
            '/expression\s*\(/i' => 'expression() is not allowed in custom CSS.',
            '/-moz-binding\s*:/i' => '-moz-binding is not allowed in custom CSS.',
            '/\bbehavior\s*:/i' => 'behavior is not allowed in custom CSS.',
            '/<\/?style\b/i' => 'HTML is not allowed in custom CSS.',
        ];

        foreach ($patterns as $pattern => $message) {
            if (preg_match($pattern, $css)) {
                throw ValidationException::withMessages(['custom_css' => $message]);
            }

            preg_match_all('/url\(\s*([\'"]?)(.*?)\1\s*\)/i', $css, $matches);
            foreach ($matches[2] as $url) {
                $url = trim($url);
                if (str_starts_with($url, '#')
                    || str_starts_with($url, '/media/')
                    || preg_match('#^data:image/(?:png|jpeg|gif|webp);base64,#i', $url)) {
                    continue;
                }

                throw ValidationException::withMessages([
                    'custom_css' => 'CSS URLs must reference same-origin /media/ paths or data images.',
                ]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function legacyColumnTokens(WebsiteTheme $theme): array
    {
        return [
            'color_primary' => $theme->color_primary,
            'color_secondary' => $theme->color_secondary,
            'color_accent' => $theme->color_accent,
            'font_pair' => $theme->font_pair,
            'radius_preset' => $theme->radius_preset,
            'shadow_preset' => $theme->shadow_preset,
            'logo_path' => $theme->logo_path,
            'logo_dark_path' => $theme->logo_dark_path,
            'favicon_path' => $theme->favicon_path,
            'banner_image' => $theme->banner_image,
            'banner_text' => $theme->banner_text,
            'custom_css' => $theme->custom_css,
        ];
    }

    private function cssCacheKey(WebsiteTheme $theme, bool $staged = false, ?array $tokens = null): string
    {
        $tokens ??= $staged ? $this->stagedTokens($theme) : $this->liveTokens($theme);

        return 'theme_css_'.$theme->id.'_'.($staged ? 'staged_' : 'live_').md5((string) json_encode($tokens));
    }
}
