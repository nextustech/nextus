<?php

namespace App\Exceptions;

use RuntimeException;

class SubResellerLimitReachedException extends RuntimeException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct('The parent reseller has reached its sub-reseller limit ('.$limit.').');
    }
}
