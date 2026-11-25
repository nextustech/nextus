<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product media upload limits
    |--------------------------------------------------------------------------
    |
    | Catalog media (images and video clips) are stored on the disk below,
    | always under the tenant-scoped prefix tenants/{tenant_id}/media/...
    | Point the disk at S3 (see config/filesystems.php) in production by
    | setting CATALOG_MEDIA_DISK=s3 and the usual AWS_* variables.
    |
    */

    'media' => [

        'disk' => env('CATALOG_MEDIA_DISK', 'local'),

        // Max upload size for a single media file, in kilobytes.
        'max_kilobytes' => (int) env('CATALOG_MEDIA_MAX_KB', 25600), // 25 MB

        // Number of image variants generated on upload: width => quality.
        'image_variants' => [
            'thumb' => ['width' => 320, 'quality' => 75],
            'medium' => ['width' => 1024, 'quality' => 82],
        ],

        'allowed_image_types' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'allowed_video_types' => ['mp4', 'webm', 'mov'],

        // External video URLs (YouTube/Vimeo/direct) allowed on media rows.
        'video_hosts' => ['youtube.com', 'youtu.be', 'vimeo.com'],

        // FQCN implementing App\Contracts\MediaScanner (virus-scan hook point).
        'scanner' => env('CATALOG_MEDIA_SCANNER', \App\Services\ContentSniffingMediaScanner::class),
    ],

];
