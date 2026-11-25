<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAddon extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['product_id', 'name', 'price', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
