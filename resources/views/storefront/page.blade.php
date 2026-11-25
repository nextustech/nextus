<!doctype html>
<html lang="{{ str_replace('_', '-', $tenant->locale ?: 'en-IN') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo['meta_title'] ?: $page->title }}</title>
    @if (!empty($seo['meta_description']))
        <meta name="description" content="{{ $seo['meta_description'] }}">
    @endif
    <meta name="robots" content="{{ $robots }}">
    @if ($canonicalUrl)
        <link rel="canonical" href="{{ $canonicalUrl }}">
    @endif
    <meta property="og:type" content="{{ $page->page_type === 'product_detail' ? 'product' : 'website' }}">
    <meta property="og:title" content="{{ $seo['meta_title'] ?: $page->title }}">
    @if (!empty($seo['meta_description']))
        <meta property="og:description" content="{{ $seo['meta_description'] }}">
    @endif
    @if ($ogImageUrl)
        <meta property="og:image" content="{{ $ogImageUrl }}">
    @endif
    <meta name="twitter:card" content="{{ $ogImageUrl ? 'summary_large_image' : 'summary' }}">
    @if ($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
    @endif
    <link rel="stylesheet" href="{{ route('storefront.theme-css') }}">
    <style>
        :root { color-scheme: light; --sf-text: #172033; --sf-muted: #64748b; --sf-surface: #fff; --sf-border: #e2e8f0; }
        * { box-sizing: border-box; }
        body { margin: 0; color: var(--sf-text); background: #f8fafc; font-family: var(--sf-font-body, system-ui, sans-serif); }
        a { color: var(--sf-brand-700, #3730a3); }
        .sf-banner { padding: .65rem 1rem; text-align: center; color: #fff; background: var(--sf-brand-600, #4f46e5); }
        .sf-header, .sf-footer { background: var(--sf-surface); border-bottom: 1px solid var(--sf-border); }
        .sf-header-inner, .sf-footer-inner, .sf-main { width: min(1120px, calc(100% - 2rem)); margin: auto; }
        .sf-header-inner { min-height: 4.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; }
        .sf-brand { min-height: 44px; display: inline-flex; align-items: center; color: inherit; font-size: 1.25rem; font-weight: 700; text-decoration: none; }
        .sf-brand img { max-height: 2.5rem; max-width: 12rem; object-fit: contain; }
        .sf-nav, .sf-menu, .sf-menu ul { display: flex; align-items: center; flex-wrap: wrap; gap: 1rem; margin: 0; padding: 0; list-style: none; }
        .sf-nav a, .sf-footer a { min-height: 44px; display: inline-flex; align-items: center; text-decoration: none; }
        .sf-menu details { position: relative; }
        .sf-menu summary { min-height: 44px; display: inline-flex; align-items: center; cursor: pointer; list-style: none; }
        .sf-menu summary::-webkit-details-marker { display: none; }
        .sf-menu details ul { position: absolute; z-index: 2; top: 100%; left: 0; min-width: 12rem; display: grid; gap: .5rem; padding: .75rem; background: #fff; border: 1px solid var(--sf-border); border-radius: var(--sf-radius, .5rem); box-shadow: var(--sf-shadow, 0 8px 24px #0001); }
        .sf-main { min-height: 65vh; padding-block: 3rem; }
        .sf-hero, .sf-section, .sf-product-card { border-radius: var(--sf-radius, .5rem); box-shadow: var(--sf-shadow, 0 8px 24px #0001); background: #fff; }
        .sf-hero { padding: clamp(2rem, 6vw, 5rem); margin-bottom: 1.5rem; background: linear-gradient(135deg, #fff, #eef2ff); }
        .sf-hero h1 { margin-top: 0; font: 700 clamp(2rem, 6vw, 3.5rem)/1.1 var(--sf-font-heading, system-ui, sans-serif); }
        .sf-section { padding: clamp(1.25rem, 3vw, 2rem); margin-block: 1.5rem; }
        .sf-section h2 { margin-top: 0; font-family: var(--sf-font-heading, system-ui, sans-serif); }
        .sf-grid, .sf-products { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 1rem; }
        .sf-product-card { overflow: hidden; color: inherit; text-decoration: none; border: 1px solid var(--sf-border); }
        .sf-product-card img { width: 100%; height: 12rem; object-fit: cover; background: #f1f5f9; }
        .sf-product-card-body { padding: 1rem; }
        .sf-button { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; padding: .65rem 1rem; border-radius: var(--sf-radius, .5rem); color: #fff; background: var(--sf-brand-600, #4f46e5); text-decoration: none; font-weight: 600; }
        .sf-media { max-width: 100%; height: auto; border-radius: var(--sf-radius, .5rem); }
        .sf-footer { border-top: 1px solid var(--sf-border); border-bottom: 0; }
        .sf-footer-inner { padding-block: 1.5rem; display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        @media (max-width: 640px) { .sf-header-inner { align-items: flex-start; flex-direction: column; padding-block: 1rem; } .sf-nav, .sf-menu { gap: .75rem; } .sf-main { padding-block: 1.5rem; } }
    </style>
    <script type="application/ld+json">{!! $jsonLd !!}</script>
</head>
<body>
    @if (!empty($theme['banner_text']))
        <div class="sf-banner">{{ $theme['banner_text'] }}</div>
    @endif
    <header class="sf-header">
        <div class="sf-header-inner">
            <a class="sf-brand" href="/" aria-label="{{ $tenant->name }} home">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $tenant->name }}">
                @else
                    {{ $tenant->name }}
                @endif
            </a>
            <nav class="sf-nav" aria-label="Main navigation">
                <ul class="sf-menu">
                    @foreach ($menus as $item)
                        @if ($item['area'] === 'primary')
                        <li>
                            @if ($item['children'])
                                <details>
                                    <summary>{{ $item['label'] }}</summary>
                                    <ul>
                                        @foreach ($item['children'] as $child)
                                            <li><a href="{{ $child['href'] }}" target="{{ $child['target'] }}" @if ($child['target'] === '_blank') rel="noopener noreferrer" @endif>{{ $child['label'] }}</a></li>
                                        @endforeach
                                    </ul>
                                </details>
                            @elseif ($item['href'])
                                <a href="{{ $item['href'] }}" target="{{ $item['target'] }}" @if ($item['target'] === '_blank') rel="noopener noreferrer" @endif>{{ $item['label'] }}</a>
                            @endif
                        </li>
                        @endif
                    @endforeach
                </ul>
            </nav>
        </div>
    </header>
    <main class="sf-main">
        @if (isset($product))
            <article>
                <h1>{{ $product['product']->name }}</h1>
                <p>{{ $product['product']->short_description }}</p>
                @if ($productMedia)
                    <div class="sf-grid">
                        @foreach ($productMedia as $image)
                            @if ($image['url'])
                                <img class="sf-media" src="{{ $image['url'] }}" alt="{{ $image['alt'] }}">
                            @endif
                        @endforeach
                    </div>
                @endif
                <p>{{ $product['price'] !== null ? number_format((float) $product['price'], 2).' '.$product['currency'] : 'Contact us for pricing' }}</p>
                @if ($demos)
                    <section class="sf-section" aria-labelledby="sf-demos-title">
                        <h2 id="sf-demos-title">Product demos</h2>
                        <ul>
                            @foreach ($demos as $demo)
                                <li><a href="{{ $demo['url'] }}" target="_blank" rel="noopener noreferrer">{{ $demo['title'] }}</a></li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </article>
        @endif

        @if (isset($products) && $page->page_type === 'product_listing')
            <section aria-label="Available products" class="sf-products">
                @forelse ($products as $row)
                    <a class="sf-product-card" href="{{ $row['href'] }}">
                        @if ($row['image'])
                            <img src="{{ $row['image'] }}" alt="">
                        @endif
                        <div class="sf-product-card-body">
                            <h2>{{ $row['product']->name }}</h2>
                            <p>{{ $row['product']->short_description }}</p>
                            <strong>{{ $row['price'] !== null ? number_format((float) $row['price'], 2).' '.$row['currency'] : 'Contact us for pricing' }}</strong>
                        </div>
                    </a>
                @empty
                    <p>No products are available yet.</p>
                @endforelse
            </section>
        @endif

        @foreach ($sections as $section)
            @php($settings = $section['settings'] ?? [])
            @switch($section['type'] ?? '')
                @case('hero')
                    <section class="sf-hero">
                        @if (!empty($settings['background_image']))
                            <img class="sf-media" src="{{ $settings['background_image'] }}" alt="">
                        @endif
                        <h1>{{ $settings['headline'] ?? $page->title }}</h1>
                        @if (!empty($settings['subheadline']))<p>{{ $settings['subheadline'] }}</p>@endif
                        @if (!empty($settings['cta_label']) && !empty($settings['cta_url']))
                            <a class="sf-button" href="{{ $settings['cta_url'] }}">{{ $settings['cta_label'] }}</a>
                        @endif
                    </section>
                    @break
                @case('feature_grid')
                    <section class="sf-section">
                        <h2>{{ $settings['title'] ?? '' }}</h2>
                        <div class="sf-grid">
                            @foreach (($settings['items'] ?? []) as $feature)
                                <article><h3>{{ $feature['heading'] ?? '' }}</h3><p>{{ $feature['body'] ?? '' }}</p></article>
                            @endforeach
                        </div>
                    </section>
                    @break
                @case('cta')
                    <section class="sf-section">
                        <h2>{{ $settings['headline'] ?? '' }}</h2>
                        @if (!empty($settings['cta_url']) && !empty($settings['cta_label']))
                            <a class="sf-button" href="{{ $settings['cta_url'] }}">{{ $settings['cta_label'] }}</a>
                        @endif
                    </section>
                    @break
                @case('faq_accordion')
                    <section class="sf-section">
                        <h2>{{ $settings['title'] ?? 'Frequently asked questions' }}</h2>
                        @foreach (($settings['items'] ?? []) as $item)
                            <details><summary>{{ $item['question'] ?? '' }}</summary><p>{{ $item['answer'] ?? '' }}</p></details>
                        @endforeach
                    </section>
                    @break
                @case('testimonials')
                    <section class="sf-section">
                        <h2>{{ $settings['title'] ?? 'Testimonials' }}</h2>
                        <div class="sf-grid">
                            @foreach (($settings['items'] ?? []) as $item)
                                <blockquote><p>{{ $item['quote'] ?? '' }}</p><cite>{{ $item['author'] ?? '' }}</cite><small>{{ $item['role'] ?? '' }}</small></blockquote>
                            @endforeach
                        </div>
                    </section>
                    @break
                @case('media')
                    @if (!empty($settings['image']))
                        <section class="sf-section">
                            <img class="sf-media" src="{{ $settings['image'] }}" alt="{{ $settings['alt'] ?? '' }}">
                            @if (!empty($settings['caption']))<p>{{ $settings['caption'] }}</p>@endif
                        </section>
                    @endif
                    @break
                @case('pricing_table')
                    @php($selectedProductIds = is_array($settings['product_ids'] ?? null) ? array_map('intval', $settings['product_ids']) : [])
                    @php($sectionProducts = array_values(array_filter($products, fn ($row) => in_array($row['product']->id, $selectedProductIds, true))))
                    <section class="sf-section">
                        @if (!empty($settings['title']))<h2>{{ $settings['title'] }}</h2>@endif
                        <div class="sf-products">
                            @forelse ($sectionProducts as $row)
                                <a class="sf-product-card" href="{{ $row['href'] }}">
                                    @if ($row['image'])<img src="{{ $row['image'] }}" alt="">@endif
                                    <div class="sf-product-card-body">
                                        <h3>{{ $row['product']->name }}</h3>
                                        <p>{{ $row['product']->short_description }}</p>
                                        <strong>{{ $row['price'] !== null ? number_format((float) $row['price'], 2).' '.$row['currency'] : 'Contact us for pricing' }}</strong>
                                    </div>
                                </a>
                            @empty
                                <p>No products are available for this pricing table.</p>
                            @endforelse
                        </div>
                    </section>
                    @break
                @case('custom_html')
                    <section class="sf-section">{!! $sanitizer->sanitize((string) ($settings['html'] ?? ''))['html'] !!}</section>
                    @break
                @case('blog_list')
                    <section class="sf-section"><h2>{{ $settings['title'] ?? 'Latest articles' }}</h2><p>Visit our latest updates and insights.</p></section>
                    @break
                @default
                    @if (!empty($settings['title']) || !empty($settings['headline']))
                        <section class="sf-section"><h2>{{ $settings['title'] ?? $settings['headline'] }}</h2></section>
                    @endif
            @endswitch
        @endforeach
    </main>
    <footer class="sf-footer">
        <div class="sf-footer-inner">
            <span>{{ $tenant->name }}</span>
            <nav aria-label="Footer navigation">
                <ul class="sf-menu">
                    @foreach ($menus as $item)
                        @if ($item['area'] === 'footer' && $item['href'])
                            <li>
                                @if ($item['children'])
                                    <details>
                                        <summary>{{ $item['label'] }}</summary>
                                        <ul>
                                            @foreach ($item['children'] as $child)
                                                <li><a href="{{ $child['href'] }}" target="{{ $child['target'] }}" @if ($child['target'] === '_blank') rel="noopener noreferrer" @endif>{{ $child['label'] }}</a></li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @else
                                    <a href="{{ $item['href'] }}" target="{{ $item['target'] }}" @if ($item['target'] === '_blank') rel="noopener noreferrer" @endif>{{ $item['label'] }}</a>
                                @endif
                            </li>
                        @endif
                    @endforeach
                </ul>
            </nav>
        </div>
    </footer>
</body>
</html>
