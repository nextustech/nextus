<?php

namespace App\Contracts;

use App\Exceptions\SuspiciousMediaException;

interface MediaScanner
{
    /**
     * Inspect an uploaded file before it is written to storage.
     *
     * @param  string  $absolutePath  path to the temporary upload on disk
     * @param  string  $declaredMime  MIME type as detected by PHP
     *
     * @throws SuspiciousMediaException when the file must be rejected
     */
    public function assertClean(string $absolutePath, string $declaredMime): void;
}
