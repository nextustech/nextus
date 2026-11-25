<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\WebsitePage;
use App\Models\WebsitePageRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PageWorkflowService
{
    /** Reserved storefront slugs — blocked per FR-CMS-024 (10 §5). */
    public const RESERVED_SLUGS = ['products', 'cart', 'checkout', 'account', 'portal', 'd', 'admin', 'api'];

    private const MAX_REVISIONS = 10;

    /** @var list<string> sanitizer warnings from the last create/update */
    public array $lastWarnings = [];

    public function __construct(private readonly HtmlAllowlistSanitizer $sanitizer) {}

    /**
     * Create a page as a draft with its first revision (FR-CMS-025).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function create(Tenant $tenant, array $attributes, ?int $actorId = null): WebsitePage
    {
        $this->lastWarnings = [];

        $pageType = (string) ($attributes['page_type'] ?? '');
        if (in_array($pageType, ['legal', 'not_found'], true)) {
            throw ValidationException::withMessages([
                'page_type' => 'Legal and 404 pages are system pages created at provisioning.',
            ]);
        }

        $slug = $this->validateSlug($attributes['slug'] ?? '', $tenant->id, null);
        $sections = $this->normalizeSections($this->sanitizeSections($attributes['sections'] ?? []));

        return DB::transaction(function () use ($tenant, $attributes, $actorId, $slug, $sections, $pageType): WebsitePage {
            $page = WebsitePage::query()->create([
                'tenant_id' => $tenant->id,
                'title' => (string) $attributes['title'],
                'slug' => $slug,
                'page_type' => $pageType,
                'status' => 'draft',
                'sections' => $sections,
                'seo' => $this->normalizeSeo($attributes['seo'] ?? []),
                'meta_title' => $attributes['seo']['meta_title'] ?? $attributes['title'] ?? null,
                'meta_description' => $attributes['seo']['meta_description'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->snapshot($page, $actorId);

            return $page;
        });
    }

    /**
     * Save the page as a draft. A published page flips back to draft with
     * unpublished changes while visitors keep serving the published revision.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function update(WebsitePage $page, array $attributes, ?int $actorId = null): WebsitePage
    {
        $this->lastWarnings = [];

        $updates = ['updated_by' => $actorId];

        if (array_key_exists('title', $attributes)) {
            $updates['title'] = (string) $attributes['title'];
        }

        if (array_key_exists('slug', $attributes)) {
            $systemSlug = $page->isSystemPage() || $page->page_type === 'product_detail';
            if ($systemSlug && $attributes['slug'] !== $page->slug) {
                throw ValidationException::withMessages([
                    'slug' => 'The slug of a system page cannot be changed.',
                ]);
            }
            $updates['slug'] = $this->validateSlug((string) $attributes['slug'], $page->tenant_id, $page->id);
        }

        if (array_key_exists('sections', $attributes)) {
            $errors = SectionCatalog::validate($attributes['sections']);
            if ($errors !== []) {
                throw ValidationException::withMessages(['sections' => $errors]);
            }
            $updates['sections'] = $this->normalizeSections($this->sanitizeSections($attributes['sections']));
        }

        if (array_key_exists('seo', $attributes)) {
            $seo = $this->normalizeSeo($attributes['seo']);
            $updates['seo'] = $seo;
            $updates['meta_title'] = $seo['meta_title'] ?? $page->meta_title;
            $updates['meta_description'] = $seo['meta_description'] ?? $page->meta_description;
        }

        // Editing a live page stages unpublished changes (diagram node I → B).
        if ($page->status === 'published') {
            $updates['status'] = 'draft';
        }

        return DB::transaction(function () use ($page, $updates, $actorId): WebsitePage {
            $page->update($updates);
            $this->snapshot($page, $actorId);

            return $page->refresh();
        });
    }

    /**
     * Server-side publish validation (FR-CMS-025, AC-FR-CMS-025-1).
     *
     * @throws ValidationException
     */
    public function publish(WebsitePage $page, ?int $actorId = null): WebsitePage
    {
        $this->lastWarnings = [];

        $sections = $this->sanitizeSections($page->sections ?? []);
        $errors = SectionCatalog::validate($sections);
        if ($errors !== []) {
            throw ValidationException::withMessages(['sections' => $errors]);
        }

        return DB::transaction(function () use ($page, $sections, $actorId): WebsitePage {
            $sections = $this->normalizeSections($sections);
            $revision = $this->snapshot($page->forceFill([
                'sections' => $sections,
                'updated_by' => $actorId,
            ]), $actorId);

            $page->update([
                'sections' => $sections,
                'status' => 'published',
                'published_at' => now(),
                'published_revision_id' => $revision->id,
                'published_slug' => $page->slug,
                'updated_by' => $actorId,
            ]);

            return $page->refresh();
        });
    }

    /**
     * Unpublish: legal pages are required and cannot leave the published
     * state (AC-FR-CMS-030-1).
     *
     * @throws ValidationException
     */
    public function unpublish(WebsitePage $page, ?int $actorId = null): WebsitePage
    {
        if ($page->isLegal()) {
            throw ValidationException::withMessages([
                'status' => 'legal pages are required',
            ]);
        }

        $page->update([
            'status' => 'draft',
            'published_revision_id' => null,
            'published_slug' => null,
            'published_at' => null,
            'updated_by' => $actorId,
        ]);

        return $page->refresh();
    }

    /**
     * System pages (privacy/terms/404) are singletons and cannot be deleted.
     *
     * @throws ValidationException
     */
    public function delete(WebsitePage $page, ?int $actorId = null): void
    {
        if ($page->isSystemPage()) {
            throw ValidationException::withMessages([
                'page' => 'System pages cannot be deleted.',
            ]);
        }

        $page->delete();
    }

    /**
     * Non-destructive restore: revision content is copied into a NEW draft
     * revision; the original revision is never modified (FR-CMS-029).
     *
     * @throws ValidationException
     */
    public function restore(WebsitePage $page, int $revisionId, ?int $actorId = null): WebsitePage
    {
        $revision = $page->revisions()->whereKey($revisionId)->first();
        if ($revision === null) {
            throw ValidationException::withMessages(['revision' => 'Revision not found.']);
        }

        return DB::transaction(function () use ($page, $revision, $actorId): WebsitePage {
            $page->update([
                'title' => $revision->title,
                'sections' => $revision->sections,
                'seo' => $revision->seo,
                'status' => 'draft',
                'updated_by' => $actorId,
            ]);

            $this->snapshot($page, $actorId);

            return $page->refresh();
        });
    }

    /**
     * Reorder sections to the given id sequence (FR-CMS-019).
     *
     * @param  list<string>  $orderedIds
     *
     * @throws ValidationException
     */
    public function reorderSections(WebsitePage $page, array $orderedIds, ?int $actorId = null): WebsitePage
    {
        $sections = $page->sections ?? [];
        $byId = [];
        foreach ($sections as $section) {
            $byId[$section['id'] ?? ''] = $section;
        }

        if (count($orderedIds) !== count($sections)
            || array_diff($orderedIds, array_keys($byId)) !== []
            || array_diff(array_keys($byId), $orderedIds) !== []) {
            throw ValidationException::withMessages([
                'order' => 'Section order must contain every section id exactly once.',
            ]);
        }

        $reordered = [];
        foreach ($orderedIds as $id) {
            $reordered[] = $byId[$id];
        }

        $updates = ['sections' => $reordered, 'updated_by' => $actorId];
        if ($page->status === 'published') {
            $updates['status'] = 'draft';
        }

        $page->update($updates);

        return $page->refresh();
    }

    /**
     * Signed, expiring (7-day) preview URL rendering unpublished content
     * with X-Robots-Tag: noindex (FR-CMS-028).
     */
    public function previewUrl(WebsitePage $page): string
    {
        return URL::temporarySignedRoute('pages.preview', now()->addDays(7), [
            'id' => $page->id,
        ]);
    }

    /**
     * Sanitize custom_html sections through the shared allowlist (FR-CMS-020).
     *
     * @param  array<int, mixed>  $sections
     * @return array<int, mixed>
     */
    public function sanitizeSections(array $sections): array
    {
        foreach ($sections as $index => $section) {
            if (! is_array($section) || ($section['type'] ?? '') !== 'custom_html') {
                continue;
            }

            $html = (string) ($section['settings']['html'] ?? '');
            $result = $this->sanitizer->sanitize($html);

            $sections[$index]['settings']['html'] = $result['html'];
            foreach ($result['removed'] as $element) {
                $this->lastWarnings[] = sprintf(
                    'Section %d (custom_html): removed "%s" by the HTML allowlist sanitizer (policy %s).',
                    $index,
                    $element,
                    HtmlAllowlistSanitizer::POLICY_VERSION,
                );
            }
        }

        return $sections;
    }

    /** Snapshot the current page content as a new revision, pruned to the last 10. */
    public function snapshot(WebsitePage $page, ?int $actorId = null): WebsitePageRevision
    {
        $next = (int) $page->revisions()->max('revision') + 1;

        $revision = WebsitePageRevision::query()->create([
            'tenant_id' => $page->tenant_id,
            'website_page_id' => $page->id,
            'revision' => $next,
            'title' => $page->title,
            'sections' => $page->sections ?? [],
            'seo' => $page->seo,
            'created_by' => $actorId,
        ]);

        $staleIds = $page->revisions()->orderByDesc('revision')->pluck('id')->slice(self::MAX_REVISIONS);
        if ($staleIds->isNotEmpty()) {
            WebsitePageRevision::query()->whereIn('id', $staleIds)->delete();
        }

        return $revision;
    }

    /**
     * @throws ValidationException
     */
    private function validateSlug(string $slug, int $tenantId, ?int $ignorePageId): string
    {
        $slug = trim(strtolower($slug));

        if ($slug === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw ValidationException::withMessages([
                'slug' => 'The slug may contain lowercase letters, numbers and hyphens only.',
            ]);
        }

        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            throw ValidationException::withMessages([
                'slug' => sprintf('"%s" is a reserved storefront slug.', $slug),
            ]);
        }

        $taken = WebsitePage::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where(fn ($query) => $query->where('slug', $slug)->orWhere('published_slug', $slug))
            ->when($ignorePageId !== null, fn ($query) => $query->where('id', '!=', $ignorePageId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'slug' => 'A page with this slug already exists.',
            ]);
        }

        return $slug;
    }

    /**
     * @param  array<int, mixed>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSections(array $sections): array
    {
        $normalized = [];
        foreach (array_values($sections) as $section) {
            if (! is_array($section)) {
                continue;
            }
            $section['id'] = (string) ($section['id'] ?? Str::ulid());
            $normalized[] = $section;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $seo
     * @return array<string, string|null>
     */
    private function normalizeSeo(array $seo): array
    {
        return [
            'meta_title' => isset($seo['meta_title']) ? (string) $seo['meta_title'] : null,
            'meta_description' => isset($seo['meta_description']) ? (string) $seo['meta_description'] : null,
            'og_image' => isset($seo['og_image']) ? (string) $seo['og_image'] : null,
            'canonical' => isset($seo['canonical']) ? (string) $seo['canonical'] : null,
            'robots' => isset($seo['robots']) ? (string) $seo['robots'] : null,
        ];
    }
}
