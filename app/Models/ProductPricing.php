<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPricing extends Model
{
    protected $table = 'product_pricing';

    protected $fillable = [
        'product_id', 'product_variant_id', 'product_addon_id',
        'base_price', 'floor_price', 'floor_type', 'currency', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'floor_price' => 'decimal:2',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }
}
