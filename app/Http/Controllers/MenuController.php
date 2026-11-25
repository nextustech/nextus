<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Tenant;
use App\Models\WebsiteMenu;
use App\Models\WebsitePage;
use App\Services\AuditLogger;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MenuController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        abort_unless($request->user()->can('cms.view'), 403);
        $tenantId = app(TenantContext::class)->id();

        $menus = WebsiteMenu::query()
            ->where('tenant_id', $tenantId)
            ->with(['children' => fn ($query) => $query->where('tenant_id', $tenantId)])
            ->orderBy('name')
            ->orderBy('position')
            ->get()
            ->whereNull('parent_id')
            ->values();

        return Inertia::render('Cms/Menus', [
            'menus' => $menus,
            'pages' => WebsitePage::query()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('published_revision_id')
                ->with('publishedRevision:id,title')
                ->orderBy('title')
                ->get(['id', 'slug', 'published_slug', 'title', 'published_revision_id'])
                ->map(fn (WebsitePage $page): array => [
                    'slug' => $page->published_slug ?: $page->slug,
                    'title' => $page->publishedRevision?->title ?: $page->title,
                ]),
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('position')
                ->get(['slug', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('cms.menus_manage'), 403);
        $attributes = $this->validatedAttributes($request);
        $tenantId = app(TenantContext::class)->id();
        $attributes['tenant_id'] = $tenantId;
        $attributes['position'] = (int) WebsiteMenu::query()
            ->where('tenant_id', $tenantId)
            ->where('name', $attributes['name'])
            ->where('parent_id', $attributes['parent_id'])
            ->max('position') + 1;
        $attributes['slug'] = Str::slug($attributes['display_name']).'-'.Str::lower(Str::random(6));
        $attributes['status'] = 'published';
        $attributes['is_external'] = $attributes['target_type'] === 'url';
        $attributes['url'] = $this->urlFor($attributes['target_type'], $attributes['target']);

        $menu = WebsiteMenu::query()->create($attributes);
        app(AuditLogger::class)->record(
            'cms.menu_created',
            $menu,
            ['area' => $menu->name, 'target_type' => $menu->target_type],
            $request,
            $tenantId,
        );

        return back()->with('status', 'Menu item added.');
    }

    public function update(Request $request, int $id)
    {
        abort_unless($request->user()->can('cms.menus_manage'), 403);
        $menu = $this->ownedMenu($id);
        $attributes = $this->validatedAttributes($request, $menu);
        $attributes['is_external'] = $attributes['target_type'] === 'url';
        $attributes['url'] = $this->urlFor($attributes['target_type'], $attributes['target']);
        $before = $menu->only(['name', 'display_name', 'target_type', 'target', 'parent_id', 'is_visible', 'position']);
        $menu->update($attributes);
        app(AuditLogger::class)->record(
            'cms.menu_updated',
            $menu,
            ['before' => $before, 'after' => $menu->only(array_keys($before))],
            $request,
            (int) app(TenantContext::class)->id(),
        );

        return back()->with('status', 'Menu item updated.');
    }

    public function destroy(Request $request, int $id)
    {
        abort_unless($request->user()->can('cms.menus_manage'), 403);
        $menu = $this->ownedMenu($id);

        if (WebsiteMenu::query()->where('parent_id', $menu->id)->exists()) {
            throw ValidationException::withMessages([
                'menu' => 'Move or delete this item’s dropdown links before deleting it.',
            ]);
        }

        $menu->delete();
        app(AuditLogger::class)->record(
            'cms.menu_deleted',
            $menu,
            ['area' => $menu->name, 'label' => $menu->display_name],
            $request,
            (int) app(TenantContext::class)->id(),
        );

        return back()->with('status', 'Menu item deleted.');
    }

    public function reorder(Request $request, string $area)
    {
        abort_unless($request->user()->can('cms.menus_manage'), 403);
        abort_unless(in_array($area, ['primary', 'footer'], true), 404);
        $tenantId = app(TenantContext::class)->id();

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $existing = WebsiteMenu::query()
            ->where('tenant_id', $tenantId)
            ->where('name', $area)
            ->whereNull('parent_id')
            ->orderBy('position')
            ->pluck('id')
            ->all();
        $ids = array_map('intval', $validated['ids']);

        if (count($ids) !== count($existing) || array_diff($ids, $existing) !== []) {
            throw ValidationException::withMessages([
                'ids' => 'Reordering must include every top-level item in this menu exactly once.',
            ]);
        }

        DB::transaction(function () use ($ids, $tenantId, $area): void {
            foreach ($ids as $position => $id) {
                WebsiteMenu::query()
                    ->where('tenant_id', $tenantId)
                    ->where('name', $area)
                    ->whereNull('parent_id')
                    ->whereKey($id)
                    ->update(['position' => $position + 1]);
            }
        });
        app(AuditLogger::class)->record(
            'cms.menu_reordered',
            null,
            ['area' => $area, 'item_ids' => $ids],
            $request,
            $tenantId,
        );

        return back()->with('status', 'Menu order saved.');
    }

    /** @return array<string, mixed> */
    private function validatedAttributes(Request $request, ?WebsiteMenu $menu = null): array
    {
        $tenantId = app(TenantContext::class)->id();
        $validated = $request->validate([
            'name' => ['required', Rule::in(['primary', 'footer'])],
            'display_name' => ['required', 'string', 'max:191'],
            'target_type' => ['required', Rule::in(['page', 'url', 'category'])],
            'target' => ['required', 'string', 'max:255'],
            'target_behavior' => ['sometimes', Rule::in(['_self', '_blank'])],
            'is_visible' => ['sometimes', 'boolean'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $targetType = $validated['target_type'];
        if ($targetType === 'url') {
            $url = filter_var($validated['target'], FILTER_VALIDATE_URL);
            if ($url === false || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
                throw ValidationException::withMessages(['target' => 'External menu links must use HTTPS.']);
            }
        } elseif ($targetType === 'page') {
            $exists = WebsitePage::query()
                ->where('tenant_id', $tenantId)
                ->where(fn ($query) => $query
                    ->where('published_slug', $validated['target'])
                    ->orWhere(fn ($legacy) => $legacy->whereNull('published_slug')
                        ->where('slug', $validated['target'])
                        ->where('status', 'published')))
                ->whereNotNull('published_revision_id')
                ->exists();
            if (! $exists) {
                throw ValidationException::withMessages(['target' => 'Choose a published page from this storefront.']);
            }
        } elseif (! Category::query()->where('slug', $validated['target'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['target' => 'Choose an active product category.']);
        }

        $parentId = $validated['parent_id'] ?? null;
        if ($menu !== null
            && WebsiteMenu::query()->where('parent_id', $menu->id)->exists()
            && ($parentId !== null || $menu->name !== $validated['name'])) {
            throw ValidationException::withMessages([
                'parent_id' => 'Move or delete this item’s dropdown links before changing its menu position.',
            ]);
        }

        if ($parentId !== null) {
            $parent = WebsiteMenu::query()
                ->where('tenant_id', $tenantId)
                ->where('name', $validated['name'])
                ->whereNull('parent_id')
                ->whereKey($parentId)
                ->when($menu !== null, fn ($query) => $query->whereKeyNot($menu->id))
                ->first();

            if ($parent === null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Dropdown links must belong to a top-level item in the same menu.',
                ]);
            }
        }

        $validated['parent_id'] = $parentId;
        $validated['target_behavior'] = $validated['target_behavior'] ?? '_self';
        $validated['is_visible'] = $validated['is_visible'] ?? true;

        return $validated;
    }

    private function ownedMenu(int $id): WebsiteMenu
    {
        return WebsiteMenu::query()
            ->where('tenant_id', app(TenantContext::class)->id())
            ->findOrFail($id);
    }

    private function urlFor(string $type, string $target): string
    {
        return match ($type) {
            'url' => $target,
            'category' => '/products?category='.rawurlencode($target),
            default => $target === 'home' ? '/' : '/'.ltrim($target, '/'),
        };
    }
}
