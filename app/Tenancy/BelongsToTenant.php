<?php

namespace App\Tenancy;

use App\Exceptions\TenantContextMissingException;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if (! $context->get()) {
                throw new TenantContextMissingException;
            }

            if ($model->tenant_id !== null && (int) $model->tenant_id !== $context->id()) {
                throw new TenantContextMissingException;
            }

            $model->tenant_id = $context->id();
        });
    }

    public function initializeBelongsToTenant(): void
    {
        $this->mergeFillable(['tenant_id']);
    }
}
