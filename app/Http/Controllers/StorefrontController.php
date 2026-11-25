<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MediaLibrary;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\Tenant;
use App\Models\TenantProduct;
use App\Models\WebsiteMenu;
use App\Models\WebsitePage;
use App\Models\WebsiteTheme;
use App\Services\HtmlAllowlistSanitizer;
use App\Services\ProductPricingService;
use App\Services\StorefrontProvisioningService;
use App\Services\ThemeCustomizationService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly ThemeCustomizationService $themes,
        private readonly HtmlAllowlistSanitizer $sanitizer,
    ) {}

    public function home(Request $request): Response
    {
        return $this->renderPage($request, WebsitePage::query()
            ->where('tenant_id', $this->tenantId())
            ->where('page_type', 'homepage')
            ->whereNotNull('published_revision_id')
            ->first());
    }

    public function show(Request $request, ?string $path = null): Response
    {
        $path = trim((string) $path, '/');

        if ($path === '') {
            return $this->home($request);
        }

        if ($path === 'products') {
            return $this->productListing($request);
        }

        if (str_starts_with($path, 'products/')) {
            return $this->productDetail($request, substr($path, strlen('products/')));
        }

        $page = WebsitePage::query()
            ->where('tenant_id', $this->tenantId())
            ->where(fn ($query) => $query
                ->where('published_slug', $path)
                ->orWhere(fn ($legacy) => $legacy->whereNull('published_slug')
                    ->where('slug', $path)
                    ->where('status', 'published')))
            ->whereNotNull('published_revision_id')
            ->first();

        return $page ? $this->renderPage($request, $page) : $this->notFound($request);
    }

    public function themeCss(): Response
    {
        $this->tenantId();
        $theme = $this->theme();

        return response($this->themes->cssBlock($theme), 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function productMedia(string $slug, int $mediaId): StreamedResponse
    {
        $tenantId = $this->tenantId();
        $media = ProductMedia::query()
            ->whereKey($mediaId)
            ->where('type', ProductMedia::TYPE_IMAGE)
            ->whereHas('product', function ($query) use ($tenantId, $slug): void {
                $query->where('slug', $slug)
                    ->where('status', 'published')
                    ->whereHas('grants', fn ($grants) => $grants
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'active')
                        ->where(fn ($active) => $active->whereNull('expires_at')->orWhere('expires_at', '>', now())));
            })
            ->firstOrFail();

        $disk = Storage::disk((string) config('catalog.media.disk', 'local'));
        $mime = $disk->mimeType($media->path) ?: 'application/octet-stream';
        abort_unless(str_starts_with($mime, 'image/'), 404);
        $stream = $disk->readStream($media->path);
        abort_if($stream === false, 404);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            basename($media->path),
        );

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function robots(Request $request): Response
    {
        $this->tenantId();
        $origin = $request->getSchemeAndHttpHost();
        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /account',
            'Disallow: /portal',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /admin',
            'Disallow: /api',
            'Disallow: /d/',
            'Disallow: /preview/',
            'Sitemap: '.$origin.'/sitemap.xml',
            '',
        ]);

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function sitemap(Request $request): Response
    {
        $this->tenantId();
        $tenantId = $this->tenantId();
        $pages = WebsitePage::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('published_revision_id')
            ->where('page_type', '!=', 'not_found')
            ->orderBy('slug')
            ->with('publishedRevision:id,created_at,seo')
            ->get(['id', 'slug', 'published_slug', 'page_type', 'updated_at', 'published_at', 'published_revision_id']);
        $pages = $pages->reject(fn (WebsitePage $page): bool => str_contains(
            strtolower((string) data_get($page->publishedRevision?->seo, 'robots', '')),
            'noindex',
        ));

        $productRows = $this->sellableProducts($tenantId);
        $entries = $pages->map(fn (WebsitePage $page): array => [
            'url' => $page->page_type === 'homepage' ? $request->getSchemeAndHttpHost().'/' : $request->getSchemeAndHttpHost().'/'.($page->published_slug ?: $page->slug),
            'lastmod' => $page->publishedRevision?->created_at ?? $page->published_at,
        ])->all();

        foreach ($productRows as $productRow) {
            $entries[] = [
                'url' => $request->getSchemeAndHttpHost().'/products/'.$productRow['product']->slug,
                'lastmod' => $productRow['product']->updated_at,
            ];
        }

        $key = 'storefront_sitemap_'.$tenantId.'_'.md5(json_encode([
            'entries' => array_map(
                fn (array $entry): array => [$entry['url'], $entry['lastmod']?->timestamp],
                $entries,
            ),
            'revisions' => $pages->pluck('published_revision_id')->all(),
        ]) ?: '');
        $xml = Cache::remember($key, now()->addMinutes(10), fn (): string => view('storefront.sitemap', [
            'entries' => $entries,
        ])->render());

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    private function productListing(Request $request): Response
    {
        abort_if(app(TenantContext::class)->isPlatform(), 404);
        $tenantId = $this->tenantId();
        $products = $this->sellableProducts($tenantId);
        $category = trim((string) $request->query('category', ''));

        if ($category !== '') {
            $products = array_values(array_filter(
                $products,
                fn (array $row): bool => $row['product']->category?->slug === $category,
            ));
        }

        $page = WebsitePage::query()
            ->where('tenant_id', $tenantId)
            ->where('page_type', 'product_listing')
            ->whereNotNull('published_revision_id')
            ->first();

        if ($page === null) {
            $page = new WebsitePage([
                'title' => 'Products',
                'slug' => 'products',
                'page_type' => 'product_listing',
                'status' => 'published',
                'published_at' => now(),
                'seo' => ['meta_title' => 'Products'],
                'sections' => [],
            ]);
            $page->setRelation('publishedRevision', null);
            $page->setAttribute('tenant_id', $tenantId);
            $page->setAttribute('published_revision_id', -1);
        }

        return $this->renderPage($request, $page, ['products' => $products]);
    }

    private function productDetail(Request $request, string $slug): Response
    {
        abort_if(app(TenantContext::class)->isPlatform(), 404);
        $row = collect($this->sellableProducts($this->tenantId()))
            ->first(fn (array $item): bool => $item['product']->slug === $slug);

        if ($row === null) {
            return $this->notFound($request);
        }

        $product = $row['product'];
        $page = new WebsitePage([
            'title' => $product->name,
            'slug' => 'products/'.$product->slug,
            'page_type' => 'product_detail',
            'status' => 'published',
            'published_at' => $product->updated_at,
            'seo' => [
                'meta_title' => $product->name,
                'meta_description' => $product->short_description,
            ],
            'sections' => [],
        ]);
        $page->setAttribute('tenant_id', $this->tenantId());
        $page->setAttribute('published_revision_id', $product->updated_at?->timestamp ?? 0);

        $media = $product->media
            ->where('type', ProductMedia::TYPE_IMAGE)
            ->map(fn (ProductMedia $item): array => [
                'url' => $this->productMediaUrl($product, $item),
                'alt' => $item->alt,
            ])
            ->values()
            ->all();
        $demos = $product->demos
            ->where('status', 'active')
            ->map(fn ($demo): array => [
                'title' => $demo->title,
                'url' => $demo->live_url ?: $demo->video_url,
            ])
            ->filter(fn (array $demo): bool => is_string($demo['url']) && $this->isSafeLink($demo['url']))
            ->values()
            ->all();

        return $this->renderPage($request, $page, [
            'product' => $row,
            'productMedia' => $media,
            'demos' => $demos,
        ]);
    }

    private function renderPage(Request $request, ?WebsitePage $page, array $extra = [], int $status = 200): Response
    {
        if ($page === null) {
            return $this->notFound($request);
        }

        $tenant = Tenant::query()->findOrFail($this->tenantId());
        $theme = $this->theme();
        $menus = $this->publicMenus($tenant->id);
        $revision = $page->publishedRevision;
        $sections = $page->publicSections() ?? $page->sections ?? [];
        if ($revision !== null) {
            $page->setAttribute('title', $revision->title);
            $page->setAttribute('seo', $revision->seo ?? []);
            $page->setAttribute('slug', $page->published_slug ?: $page->slug);
        }
        $sections = app(\App\Services\PageWorkflowService::class)->sanitizeSections($sections);
        $page->setAttribute('sections', $sections);
        $seo = array_merge($page->seo ?? [], [
            'meta_title' => $page->seo['meta_title'] ?? $page->meta_title ?? $page->title,
            'meta_description' => $page->seo['meta_description'] ?? $page->meta_description,
        ]);
        $themeTokens = $this->themes->liveTokens($theme);
        $logo = $themeTokens['logo_path'] ?? $tenant->logo_path;
        $favicon = $themeTokens['favicon_path'] ?? $tenant->favicon_path;
        $ogImage = $seo['og_image'] ?? $this->defaultOgImage($page, $tenant->id) ?? $logo;
        $sections = $this->safeSections($sections);
        $products = $extra['products'] ?? $this->sellableProducts($tenant->id);
        $organization = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $tenant->name,
            'url' => $request->getSchemeAndHttpHost(),
            'logo' => $logo ? $this->absoluteAssetUrl($request, $logo) : null,
            'contactPoint' => [[
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'url' => $request->getSchemeAndHttpHost().'/contact',
            ]],
        ];
        $structuredData = [$organization];
        if (isset($extra['product'])) {
            $structuredData[] = $this->productJsonLd($extra['product'], $tenant);
        }
        $jsonLd = json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $cacheKey = 'storefront_page_'.md5(json_encode([
            'tenant' => $tenant->id,
            'host' => $request->getSchemeAndHttpHost(),
            'page' => $page->getKey(),
            'revision' => $page->published_revision_id,
            'page_updated' => $page->updated_at?->timestamp,
            'theme_updated' => $theme->updated_at?->timestamp,
            'menu_updated' => WebsiteMenu::query()->where('tenant_id', $tenant->id)->max('updated_at'),
            'product_updated' => Product::query()->whereIn('id', collect($products)->pluck('product.id'))->max('updated_at'),
            'query' => $request->query(),
        ]) ?: '');

        $html = Cache::remember($cacheKey, now()->addMinute(), fn (): string => view('storefront.page', [
            'tenant' => $tenant,
            'theme' => $themeTokens,
            'page' => $page,
            'sections' => $sections,
            'seo' => $seo,
            'menus' => $menus,
            'sanitizer' => $this->sanitizer,
            'logoUrl' => $logo ? $this->safeAssetUrl($logo) : null,
            'faviconUrl' => $favicon ? $this->safeAssetUrl($favicon) : null,
            'ogImageUrl' => $ogImage ? $this->absoluteAssetUrl($request, $ogImage) : null,
            'canonicalUrl' => $this->canonicalUrl($request, $page, $seo, $status),
            'robots' => $status === 404 ? 'noindex, nofollow' : ($seo['robots'] ?: 'index, follow'),
            'products' => $products,
            'productMedia' => $extra['productMedia'] ?? [],
            'demos' => $extra['demos'] ?? [],
            'jsonLd' => is_string($jsonLd) ? $jsonLd : '[]',
        ])->render());

        return response($html, $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => $status === 404 ? 'no-store' : 'public, max-age=60, s-maxage=60',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function notFound(Request $request): Response
    {
        abort_if(app(TenantContext::class)->isPlatform(), 404);
        $tenantId = $this->tenantId();
        $page = WebsitePage::query()
            ->where('tenant_id', $tenantId)
            ->where('system_key', 'not_found')
            ->whereNotNull('published_revision_id')
            ->first();

        if ($page === null) {
            $page = new WebsitePage([
                'title' => 'Page not found',
                'slug' => '404',
                'page_type' => 'not_found',
                'seo' => ['meta_title' => 'Page not found'],
                'sections' => [[
                    'type' => 'custom_html',
                    'settings' => ['html' => '<p>The page you requested could not be found.</p>'],
                ]],
            ]);
            $page->setAttribute('tenant_id', $tenantId);
            $page->setAttribute('published_revision_id', -1);
        }

        return $this->renderPage($request, $page, [], 404);
    }

    /** @return list<array{item: Product, price: ?string, currency: string, href: string, image: ?string}> */
    private function sellableProducts(int $tenantId): array
    {
        $grants = TenantProduct::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with(['product.category', 'product.media', 'product.demos'])
            ->get();

        $products = [];
        foreach ($grants as $grant) {
            $product = $grant->product;
            if ($product === null || $product->status !== 'published') {
                continue;
            }

            $pricing = app(ProductPricingService::class)->effectivePrice($product, null, $this->currency());
            $primaryMedia = $product->media->first(
                fn (ProductMedia $media): bool => $media->type === ProductMedia::TYPE_IMAGE && $media->is_primary,
            ) ?? $product->media->first(fn (ProductMedia $media): bool => $media->type === ProductMedia::TYPE_IMAGE);

            $floor = $pricing !== null ? app(ProductPricingService::class)->floorMinorUnits($pricing) / 100 : 0;
            $price = $grant->price_override;
            if ($pricing !== null) {
                $price = max((float) ($price ?? $pricing->base_price), $floor);
            }
            $products[] = [
                'product' => $product,
                'price' => $price,
                'currency' => $pricing?->currency ?? $this->currency(),
                'href' => '/products/'.$product->slug,
                'image' => $primaryMedia ? $this->productMediaUrl($product, $primaryMedia) : null,
            ];
        }

        return $products;
    }

    /** @return list<array<string, mixed>> */
    private function publicMenus(int $tenantId): array
    {
        return WebsiteMenu::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('name', ['primary', 'footer'])
            ->where('status', 'published')
            ->where('is_visible', true)
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query
                ->where('tenant_id', $tenantId)
                ->where('status', 'published')
                ->where('is_visible', true)])
            ->orderBy('position')
            ->get()
            ->map(fn (WebsiteMenu $item): array => $this->formatMenuItem($item))
            ->all();
    }

    /** @return array<string, mixed> */
    private function formatMenuItem(WebsiteMenu $item): array
    {
        $type = $item->target_type ?? ($item->is_external ? 'url' : 'page');
        $target = $item->target ?? $item->url ?? $item->slug;
        $href = match ($type) {
            'url' => $this->isHttpsUrl((string) $target) ? (string) $target : null,
            'category' => '/products?category='.rawurlencode((string) $target),
            default => $target === 'home' ? '/' : '/'.ltrim((string) ($item->url ?: $target), '/'),
        };

        return [
            'area' => $item->name,
            'label' => $item->display_name,
            'href' => $href,
            'target' => $item->target_behavior ?? '_self',
            'children' => $item->children
                ->map(fn (WebsiteMenu $child): array => $this->formatMenuItem($child))
                ->filter(fn (array $child): bool => $child['href'] !== null)
                ->values()
                ->all(),
        ];
    }

    private function defaultOgImage(WebsitePage $page, int $tenantId): ?string
    {
        foreach ($page->publicSections() ?? [] as $section) {
            $path = $section['settings']['image'] ?? null;
            if (is_string($path) && $path !== '') {
                return $path;
            }
        }

        $mediaId = MediaLibrary::query()
            ->where('tenant_id', $tenantId)
            ->where('is_public', true)
            ->where('type', 'image')
            ->oldest()
            ->value('id');

        return $mediaId ? '/media/'.$mediaId : null;
    }

    private function productJsonLd(array $row, Tenant $tenant): array
    {
        /** @var Product $product */
        $product = $row['product'];
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $product->short_description,
            'image' => $row['image'] ? url($row['image']) : null,
            'offers' => [
                '@type' => 'Offer',
                'price' => $row['price'],
                'priceCurrency' => $row['currency'],
                'availability' => 'https://schema.org/InStock',
                'seller' => ['@type' => 'Organization', 'name' => $tenant->name],
            ],
        ];
    }

    private function canonicalUrl(Request $request, WebsitePage $page, array $seo, int $status): ?string
    {
        if ($status === 404) {
            return null;
        }

        $override = $seo['canonical'] ?? null;
        if (is_string($override) && $this->isHttpsUrl($override)) {
            return $override;
        }

        $path = $page->page_type === 'homepage' ? '/' : '/'.ltrim($page->published_slug ?: $page->slug, '/');

        return $request->getSchemeAndHttpHost().$path;
    }

    private function safeAssetUrl(string $path): ?string
    {
        if (str_starts_with($path, '/media/')) {
            return $path;
        }

        return $this->isSafeLink($path) ? $path : null;
    }

    private function absoluteAssetUrl(Request $request, string $path): ?string
    {
        $safe = $this->safeAssetUrl($path);

        return $safe === null ? null : (str_starts_with($safe, '/') ? $request->getSchemeAndHttpHost().$safe : $safe);
    }

    private function isSafeLink(string $url): bool
    {
        if (preg_match('/[\x00-\x20\x7f<>"\'\\\\]/', $url)) {
            return false;
        }

        return (str_starts_with($url, '/') && ! str_starts_with($url, '//')) || $this->isHttpsUrl($url);
    }

    private function isHttpsUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private function productMediaUrl(Product $product, ProductMedia $media): ?string
    {
        if ($media->url !== null && $this->isHttpsUrl($media->url)) {
            return $media->url;
        }

        if ($media->path === null || $media->path === '') {
            return null;
        }

        return route('storefront.product-media', [$product->slug, $media->id]);
    }

    private function theme(): WebsiteTheme
    {
        $tenantId = $this->tenantId();
        $theme = WebsiteTheme::query()->where('tenant_id', $tenantId)->first();
        if ($theme !== null) {
            return $theme;
        }

        $tenant = Tenant::query()->findOrFail($tenantId);
        app(StorefrontProvisioningService::class)->provision($tenant);

        return WebsiteTheme::query()->where('tenant_id', $tenantId)->firstOrFail();
    }

    private function tenantId(): int
    {
        abort_if(app(TenantContext::class)->get() === null || app(TenantContext::class)->isPlatform(), 404);

        return app(TenantContext::class)->id();
    }

    private function currency(): string
    {
        return app(TenantContext::class)->get()?->currency ?? 'INR';
    }

    /**
     * Limit visitor-facing links and image sources to HTTPS, local paths and
     * media URLs even if legacy page content predates the section validators.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    private function safeSections(array $sections): array
    {
        foreach ($sections as &$section) {
            $settings = $section['settings'] ?? null;
            if (! is_array($settings)) {
                continue;
            }

            foreach (['cta_url'] as $key) {
                if (isset($settings[$key]) && (! is_string($settings[$key]) || ! $this->isSafeLink($settings[$key]))) {
                    $settings[$key] = null;
                }
            }
            foreach (['image', 'background_image'] as $key) {
                if (isset($settings[$key])) {
                    $settings[$key] = is_string($settings[$key]) ? $this->safeAssetUrl($settings[$key]) : null;
                }
            }
            $section['settings'] = $settings;
        }
        unset($section);

        return $sections;
    }
}
