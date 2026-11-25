<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\MediaLibrary;
use App\Models\WebsitePage;
use App\Services\PageWorkflowService;
use App\Services\SectionCatalog;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class WebsitePageController extends Controller
{
    public function __construct(private readonly PageWorkflowService $pages) {}

    public function index(Request $request): InertiaResponse
    {
        abort_unless($request->user()->can('cms.view'), 403);

        $tenantId = app(TenantContext::class)->id();

        return Inertia::render('Cms/Pages', [
            'pages' => WebsitePage::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('updated_at')
                ->get(['id', 'title', 'slug', 'page_type', 'status', 'published_at', 'system_key', 'updated_at'])
                ->map(fn (WebsitePage $page) => $page->toArray() + [
                    'has_unpublished_changes' => $page->hasUnpublishedChanges(),
                ]),
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        abort_unless($request->user()->can('cms.pages_manage'), 403);

        return Inertia::render('Cms/PageEditor', [
            'page' => null,
            'catalog' => SectionCatalog::types(),
            'reservedSlugs' => PageWorkflowService::RESERVED_SLUGS,
            'revisions' => [],
            'media' => $this->mediaForCurrentTenant(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('cms.pages_manage'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'slug' => ['required', 'string', 'max:191'],
            'page_type' => ['required', 'string', 'in:homepage,product_listing,product_detail,pricing,about,contact,blog_list,blog_post,faq,testimonials,portfolio'],
            'sections' => ['sometimes', 'array'],
            'seo' => ['sometimes', 'array'],
            'seo.meta_title' => ['nullable', 'string', 'max:60'],
            'seo.meta_description' => ['nullable', 'string', 'max:160'],
            'seo.og_image' => ['nullable', 'string', 'max:512'],
            'seo.canonical' => ['nullable', 'url:http,https', 'max:2048'],
            'seo.robots' => ['nullable', Rule::in(['index, follow', 'noindex, nofollow', 'index, nofollow', 'noindex, follow'])],
        ]);

        $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->id());
        $page = $this->pages->create($tenant, $validated, $request->user()->id);

        return redirect()
            ->route('pages.edit', $page->id)
            ->with('status', 'Draft created.')
            ->with('warnings', $this->pages->lastWarnings);
    }

    public function edit(Request $request, int $id): InertiaResponse
    {
        abort_unless($request->user()->can('cms.view'), 403);

        $page = $this->pageOrFail($id);

        return Inertia::render('Cms/PageEditor', [
            'page' => $page,
            'catalog' => SectionCatalog::types(),
            'reservedSlugs' => PageWorkflowService::RESERVED_SLUGS,
            'revisions' => $page->revisions()->get(),
            'previewUrl' => $this->pages->previewUrl($page),
            'media' => $this->mediaForCurrentTenant(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        abort_unless($request->user()->can('cms.pages_manage'), 403);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:191'],
            'slug' => ['sometimes', 'string', 'max:191'],
            'sections' => ['sometimes', 'array'],
            'seo' => ['sometimes', 'array'],
            'seo.meta_title' => ['nullable', 'string', 'max:60'],
            'seo.meta_description' => ['nullable', 'string', 'max:160'],
            'seo.og_image' => ['nullable', 'string', 'max:512'],
            'seo.canonical' => ['nullable', 'url:http,https', 'max:2048'],
            'seo.robots' => ['nullable', Rule::in(['index, follow', 'noindex, nofollow', 'index, nofollow', 'noindex, follow'])],
        ]);

        $page = $this->pages->update($this->pageOrFail($id), $validated, $request->user()->id);

        return back()
            ->with('status', 'Draft saved.')
            ->with('warnings', $this->pages->lastWarnings);
    }

    public function publish(Request $request, int $id)
    {
        abort_unless($request->user()->can('cms.pages_manage'), 403);

        $this->pages->publish($this->pageOrFail($id), $request->user()->id);

        return back()->with('status', 'Page published.');
    }

    public function unpublish(Request $request, int $id)
    {
        abort_unless($request->user()->can('cms.pages_manage'), 403);

        $this->pages->unpublish($this->pageOrFail($id), $request->user()->id);

        return back()->with('status', 'Page unpublished.');
    }

    public function destroy(Request $request, int $id)
    {
        abort_unless($request->user()->can('cms.pages_manage'), 403);

        $this->pages->delete($this->pageOrFail($id), $request->user()->id);

        return redirect()
            ->route('pages.index')
            ->with('status', 'Page deleted.');
    }

    public function reorder(Request $request, int $id)
    {
        abort_unless($request->user()->can('cms.pages_manage'), 403);

        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['string'],
        ]);

        $this->pages->reorderSections($this->pageOrFail($id), $validated['order'], $request->user()->id);

        return back()->with('status', 'Section order saved.');
    }

    public function restore(Request $request, int $id, int $revisionId)
    {
        abort_unless($request->user()->can('cms.pages_manage'), 403);

        $this->pages->restore($this->pageOrFail($id), $revisionId, $request->user()->id);

        return back()->with('status', 'Revision restored into a new draft.');
    }

    /**
     * Signed, expiring draft preview (FR-CMS-028). Invalid or expired
     * signatures return 404 per AC-FR-CMS-028-1.
     */
    public function preview(Request $request, int $id): Response
    {
        $page = WebsitePage::query()
            ->whereKey($id)
            ->where('deleted_at', null)
            ->firstOrFail();

        if (! $request->hasValidSignature()) {
            abort(404);
        }

        $sections = $page->sections ?? [];
        $title = $page->meta_title ?? $page->title;

        $body = '';
        foreach ($sections as $section) {
            $type = (string) ($section['type'] ?? '');
            $settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];

            $body .= match ($type) {
                'hero' => sprintf(
                    '<section class="sf-hero"><h1>%s</h1><p>%s</p></section>',
                    e($settings['headline'] ?? ''),
                    e($settings['subheadline'] ?? ''),
                ),
                'cta' => sprintf(
                    '<section class="sf-cta"><h2>%s</h2><a href="%s">%s</a></section>',
                    e($settings['headline'] ?? ''),
                    e($settings['cta_url'] ?? '#'),
                    e($settings['cta_label'] ?? ''),
                ),
                'custom_html' => (string) ($settings['html'] ?? ''),
                default => sprintf(
                    '<section class="sf-%s">%s</section>',
                    e($type),
                    e($settings['title'] ?? $settings['headline'] ?? ''),
                ),
            };
        }

        $html = <<<HTML
<!doctype html>
<html lang="en-IN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Draft preview: {$title}</title>
</head>
<body>
<div data-preview-banner>Draft preview — not visible to visitors</div>
<main>{$body}</main>
</body>
</html>
HTML;

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function pageOrFail(int $id): WebsitePage
    {
        return WebsitePage::query()
            ->where('tenant_id', app(TenantContext::class)->id())
            ->findOrFail($id);
    }

    /** @return list<array{id: int, name: string, url: string, alt_text: ?string}> */
    private function mediaForCurrentTenant(): array
    {
        return MediaLibrary::query()
            ->where('tenant_id', app(TenantContext::class)->id())
            ->where('is_public', true)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'alt_text'])
            ->map(fn (MediaLibrary $media): array => [
                'id' => $media->id,
                'name' => $media->name,
                'url' => '/media/'.$media->id,
                'alt_text' => $media->alt_text,
            ])
            ->all();
    }
}
