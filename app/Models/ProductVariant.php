<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $attributes = [
        'options' => '{}',
    ];

    protected $fillable = ['product_id', 'name', 'sku', 'options', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_active' => 'boolean'];
    }
}
