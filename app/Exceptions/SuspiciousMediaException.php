<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an uploaded media file fails content inspection
 * (virus scan / polyglot detection) and must not be persisted.
 */
class SuspiciousMediaException extends RuntimeException {}
