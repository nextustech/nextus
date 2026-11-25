<?php

use App\Services\MediaLibraryService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('cms:media-purge', function (MediaLibraryService $media) {
    $count = $media->purgeExpired();
    $this->info("Purged {$count} expired media file(s).");
})->purpose('Purge media files after the 30-day trash retention period');

Schedule::command('cms:media-purge')->daily()->withoutOverlapping();
