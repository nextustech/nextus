<?php

namespace App\Services;

use App\Contracts\MediaScanner;
use App\Models\MediaLibrary;
use App\Models\Tenant;
use DOMDocument;
use DOMElement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Illuminate\Support\Carbon;

class MediaLibraryService
{
    public const MAX_FILE_BYTES = 26214400;

    public const TENANT_QUOTA_BYTES = 2147483648;

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        'application/pdf' => 'pdf',
    ];

    public function upload(int $tenantId, ?int $userId, UploadedFile $file, ?string $altText): MediaLibrary
    {
        $mime = strtolower((string) $file->getMimeType());
        if (! isset(self::ALLOWED_MIME_TYPES[$mime])) {
            throw ValidationException::withMessages(['file' => 'This file type is not supported.']);
        }

        $size = (int) $file->getSize();
        if ($size > self::MAX_FILE_BYTES) {
            throw ValidationException::withMessages(['file' => 'Files may not exceed 25 MB.']);
        }

        $isImage = str_starts_with($mime, 'image/');
        if ($isImage && trim((string) $altText) === '') {
            throw ValidationException::withMessages(['alt_text' => 'Alt text is required for images.']);
        }

        $contents = $file->get();
        if ($mime === 'image/svg+xml') {
            $contents = $this->sanitizeSvg($contents);
            $size = strlen($contents);
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'cms-media-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to allocate a temporary media scan file.');
        }

        try {
            if (file_put_contents($temporaryPath, $contents) === false) {
                throw new RuntimeException('Unable to prepare media for security scanning.');
            }
            app(MediaScanner::class)->assertClean($temporaryPath, $mime);

            return DB::transaction(function () use ($tenantId, $userId, $file, $altText, $mime, $size, $contents): MediaLibrary {
                Tenant::query()->lockForUpdate()->findOrFail($tenantId);
                $usedBytes = MediaLibrary::query()
                    ->where('tenant_id', $tenantId)
                    ->get(['size'])
                    ->sum(fn (MediaLibrary $media): int => (int) $media->size);

                if ($usedBytes + $size > self::TENANT_QUOTA_BYTES) {
                    throw ValidationException::withMessages([
                        'file' => 'This upload would exceed the 2 GB storefront media quota.',
                    ]);
                }

                $disk = $this->disk();
                $directory = sprintf('tenants/%d/media', $tenantId);
                $filename = Str::uuid()->toString().'.'.self::ALLOWED_MIME_TYPES[$mime];
                $path = $directory.'/'.$filename;
                $stored = $disk->put($path, $contents);

                if (! $stored) {
                    throw new RuntimeException('Media storage failed.');
                }

                $isImage = str_starts_with($mime, 'image/');
                $dimensions = $isImage ? @getimagesizefromstring($contents) : false;

                try {
                    return MediaLibrary::query()->create([
                        'tenant_id' => $tenantId,
                        'uploaded_by' => $userId,
                        'name' => Str::limit($file->getClientOriginalName(), 120, ''),
                        'path' => $path,
                        'original_name' => Str::limit($file->getClientOriginalName(), 120, ''),
                        'mime_type' => $mime,
                        'size' => (string) $size,
                        'type' => $isImage ? 'image' : ($mime === 'video/mp4' ? 'video' : 'document'),
                        'is_public' => true,
                        'alt_text' => $altText,
                        'width' => $dimensions[0] ?? null,
                        'height' => $dimensions[1] ?? null,
                        'checksum' => hash('sha256', $contents),
                    ]);
                } catch (\Throwable $exception) {
                    $disk->delete($path);
                    throw $exception;
                }
            });
        } finally {
            @unlink($temporaryPath);
        }
    }

    public function purgeExpired(): int
    {
        $purged = 0;
        $cutoff = Carbon::now()->subDays(30);
        $disk = $this->disk();

        MediaLibrary::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($items) use ($disk, &$purged): void {
                foreach ($items as $item) {
                    $prefix = sprintf('tenants/%d/media/', $item->tenant_id);
                    if (! str_starts_with($item->path, $prefix)) {
                        throw new RuntimeException('Refusing to purge media outside its tenant storage directory.');
                    }

                    if (! $disk->delete($item->path) && $disk->exists($item->path)) {
                        throw new RuntimeException('Unable to delete expired media from storage.');
                    }

                    $item->forceDelete();
                    $purged++;
                }
            });

        return $purged;
    }

    /**
     * Remove executable SVG content and references to remote resources.
     */
    private function sanitizeSvg(string $contents): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || strtolower($document->documentElement?->localName ?? '') !== 'svg') {
            throw ValidationException::withMessages(['file' => 'The SVG file is invalid.']);
        }

        foreach (iterator_to_array($document->getElementsByTagName('*')) as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            if (in_array(strtolower($element->localName), ['script', 'foreignobject', 'iframe', 'object', 'embed'], true)) {
                $element->parentNode?->removeChild($element);

                continue;
            }
            if (strtolower($element->localName) === 'style') {
                $element->parentNode?->removeChild($element);

                continue;
            }

            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->localName);
                $value = trim($attribute->value);

                if (str_starts_with($name, 'on')
                    || $name === 'style'
                    || (str_contains(strtolower($value), 'url(') && ! preg_match('/url\\(\\s*[\'"]?#/i', $value))
                    || (in_array($name, ['href', 'src'], true) && $value !== ''
                        && ! str_starts_with($value, '#')
                        && ! preg_match('#^data:image/(?:png|jpeg|gif|webp);base64,#i', $value))) {
                    $element->removeAttributeNode($attribute);
                }
            }
        }

        return (string) $document->saveXML();
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk((string) config('catalog.media.disk', 'local'));
    }
}
