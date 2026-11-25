<?php

namespace App\Tenancy;

use App\Models\Tenant;

readonly class TenantInfo
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $type,
        public string $status,
        public string $currency,
        public ?int $planId,
    ) {}

    public static function fromModel(Tenant $tenant): self
    {
        return new self(
            id: $tenant->id,
            slug: $tenant->slug,
            type: $tenant->type,
            status: $tenant->status,
            currency: $tenant->currency,
            planId: null,
        );
    }
}
