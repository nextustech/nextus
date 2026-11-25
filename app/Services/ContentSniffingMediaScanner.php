<?php

namespace App\Services;

use App\Contracts\MediaScanner;
use App\Exceptions\SuspiciousMediaException;

/**
 * Default content-sniffing scanner: verifies the file's real bytes match an
 * allowed image/video MIME type and rejects script/executable payloads.
 * Swap for a ClamAV/S3-object-scanner implementation via the
 * catalog.media.scanner config key.
 */
class ContentSniffingMediaScanner implements MediaScanner
{
    private const BLOCKED = [
        'text/x-php',
        'application/x-php',
        'application/x-httpd-php',
        'text/x-shellscript',
        'application/x-executable',
        'application/x-dosexec',
        'application/x-msdownload',
        'application/x-msdos-program',
        'text/x-perl',
        'text/x-python',
    ];

    public function assertClean(string $absolutePath, string $declaredMime): void
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $actual = $finfo->file($absolutePath) ?: 'application/octet-stream';

        if (in_array(strtolower($actual), self::BLOCKED, true)) {
            throw new SuspiciousMediaException('blocked_content_type');
        }

        if (in_array(strtolower($actual), ['application/x-empty', 'inode/x-empty'], true)) {
            // Empty payloads are rejected by the mimes rule on real uploads;
            // let format/size validation report the error first.
            return;
        }

        $isDeclaredImage = str_starts_with($declaredMime, 'image/');
        $isActualImage = str_starts_with($actual, 'image/');
        $isDeclaredVideo = str_starts_with($declaredMime, 'video/');
        $isActualVideo = str_starts_with($actual, 'video/');

        if (($isDeclaredImage && ! $isActualImage) || ($isDeclaredVideo && ! $isActualVideo)) {
            throw new SuspiciousMediaException('content_type_mismatch');
        }
    }
}
