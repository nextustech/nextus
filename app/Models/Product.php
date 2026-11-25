<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'public_id', 'owner_tenant_id', 'category_id', 'product_type', 'name', 'slug',
        'short_description', 'description', 'status', 'marketplace_status', 'price_type',
        'fulfillment_model', 'fulfillment_driver', 'fulfillment_config',
        'promised_delivery_days', 'max_revisions', 'license_policy',
        're_deliverable_on_renewal', 'subscription_plan_id', 'active_version_id',
    ];

    protected function casts(): array
    {
        return [
            'fulfillment_config' => 'array',
            'license_policy' => 'array',
            're_deliverable_on_renewal' => 'boolean',
            'rating_avg' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class, 'active_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProductVersion::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('position');
    }

    public function pricing(): HasMany
    {
        return $this->hasMany(ProductPricing::class);
    }

    public function grants(): HasMany
    {
        return $this->hasMany(TenantProduct::class);
    }

    public function demos(): HasMany
    {
        return $this->hasMany(ProductDemo::class);
    }
}
