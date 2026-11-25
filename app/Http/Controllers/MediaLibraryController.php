<?php

namespace App\Http\Controllers;

use App\Models\MediaLibrary;
use App\Models\WebsitePage;
use App\Models\WebsiteTheme;
use App\Services\MediaLibraryService;
use App\Services\AuditLogger;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaLibraryController extends Controller
{
    public function __construct(private readonly MediaLibraryService $media) {}

    public function index(Request $request): InertiaResponse
    {
        abort_unless($request->user()->can('cms.view'), 403);
        $tenantId = app(TenantContext::class)->id();
        $usedBytes = MediaLibrary::withTrashed()
            ->where('tenant_id', $tenantId)
            ->sum('size');

        return Inertia::render('Cms/MediaLibrary', [
            'media' => MediaLibrary::query()
                ->where('tenant_id', $tenantId)
                ->latest()
                ->get()
                ->map(fn (MediaLibrary $item) => $this->present($item)),
            'quota' => [
                'used_bytes' => $usedBytes,
                'limit_bytes' => MediaLibraryService::TENANT_QUOTA_BYTES,
                'warning' => $usedBytes >= MediaLibraryService::TENANT_QUOTA_BYTES * 0.8,
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('cms.media_manage'), 403);
        $request->validate([
            'file' => ['required', 'file', 'max:25600', 'mimes:jpg,jpeg,png,webp,svg,gif,mp4,pdf'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $item = $this->media->upload(
            app(TenantContext::class)->id(),
            $request->user()->id,
            $request->file('file'),
            $request->input('alt_text'),
        );
        app(AuditLogger::class)->record(
            'cms.media_uploaded',
            $item,
            ['mime_type' => $item->mime_type, 'size' => $item->size, 'checksum' => $item->checksum],
            $request,
            (int) $item->tenant_id,
        );

        return back()->with('status', 'Media uploaded.')->with('uploadedMediaId', $item->id);
    }

    public function update(Request $request, int $id)
    {
        abort_unless($request->user()->can('cms.media_manage'), 403);
        $item = $this->ownedMedia($id);
        $validated = $request->validate([
            'alt_text' => ['required', 'string', 'max:255'],
        ]);
        $previousAlt = $item->alt_text;
        $item->update($validated);
        app(AuditLogger::class)->record(
            'cms.media_updated',
            $item,
            ['before_alt_text' => $previousAlt, 'after_alt_text' => $item->alt_text],
            $request,
            (int) $item->tenant_id,
        );

        return back()->with('status', 'Alt text updated.');
    }

    public function destroy(Request $request, int $id)
    {
        abort_unless($request->user()->can('cms.media_manage'), 403);
        $item = $this->ownedMedia($id);
        $references = $this->references($item);

        if ($references !== []) {
            throw ValidationException::withMessages([
                'media' => sprintf(
                    'This file is referenced by %d %s: %s',
                    count($references),
                    count($references) === 1 ? 'page or theme' : 'pages or themes',
                    implode(', ', $references),
                ),
            ]);
        }

        $item->delete();
        app(AuditLogger::class)->record(
            'cms.media_deleted',
            $item,
            ['path' => $item->path],
            $request,
            (int) $item->tenant_id,
        );

        return back()->with('status', 'Media moved to trash.');
    }

    public function show(Request $request, int $id): StreamedResponse
    {
        $item = MediaLibrary::query()
            ->where('tenant_id', app(TenantContext::class)->id())
            ->where('is_public', true)
            ->findOrFail($id);

        $disk = Storage::disk((string) config('catalog.media.disk', 'local'));
        $stream = $disk->readStream($item->path);
        abort_if($stream === false, 404);

        $inline = str_starts_with($item->mime_type, 'image/');
        $disposition = HeaderUtils::makeDisposition(
            $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
            basename($item->original_name),
        );

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $item->mime_type,
            'Content-Length' => (string) $item->size,
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** @return array<string, mixed> */
    private function present(MediaLibrary $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'original_name' => $item->original_name,
            'mime_type' => $item->mime_type,
            'size' => $item->size,
            'type' => $item->type,
            'alt_text' => $item->alt_text,
            'width' => $item->width,
            'height' => $item->height,
            'url' => route('storefront.media', $item->id),
            'references' => $this->references($item),
            'created_at' => $item->created_at,
        ];
    }

    /** @return list<string> */
    private function references(MediaLibrary $item): array
    {
        $tenantId = (int) $item->tenant_id;
        $needles = [$item->path, '/media/'.$item->id, 'media/'.$item->id];
        $references = [];

        foreach (WebsitePage::query()
            ->where('tenant_id', $tenantId)
            ->with('publishedRevision:id,sections')
            ->get(['id', 'title', 'sections', 'published_revision_id']) as $page) {
            $sections = json_encode([
                $page->sections ?? [],
                $page->seo ?? [],
                $page->publishedRevision?->sections ?? [],
                $page->publishedRevision?->seo ?? [],
            ]);
            if ($sections !== false && $this->containsAny($sections, $needles)) {
                $references[] = $page->title;
            }
        }

        $theme = WebsiteTheme::query()->where('tenant_id', $tenantId)->first();
        if ($theme !== null) {
            $tokens = json_encode([
                $theme->published_config,
                $theme->draft_config,
                $theme->logo_path,
                $theme->logo_dark_path,
                $theme->favicon_path,
                $theme->banner_image,
            ]);
            if ($tokens !== false && $this->containsAny($tokens, $needles)) {
                $references[] = 'Branding theme';
            }
        }

        return array_values(array_unique($references));
    }

    /** @param list<string> $needles */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function ownedMedia(int $id): MediaLibrary
    {
        return MediaLibrary::query()
            ->where('tenant_id', app(TenantContext::class)->id())
            ->findOrFail($id);
    }
}
