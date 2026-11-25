<?php

namespace App\Tenancy;

use App\Exceptions\TenantContextMissingException;
use App\Exceptions\TenantSuspendedException;

class TenantContext
{
    public function __construct(private ?TenantInfo $tenant = null) {}

    public function get(): ?TenantInfo
    {
        return $this->tenant;
    }

    public function bind(TenantInfo $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    public function id(): int
    {
        if (! $this->tenant) {
            throw new TenantContextMissingException;
        }

        return $this->tenant->id;
    }

    public function isPlatform(): bool
    {
        return $this->tenant?->type === 'platform';
    }

    public function assertActive(): void
    {
        if (! $this->tenant) {
            throw new TenantContextMissingException;
        }

        if ($this->tenant->status !== 'active') {
            throw new TenantSuspendedException;
        }
    }

    public function is(int $tenantId): bool
    {
        return $this->tenant?->id === $tenantId;
    }
}
