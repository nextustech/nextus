<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageItem extends Model
{
    protected $fillable = [
        'product_package_id', 'component_product_id',
        'quantity', 'component_snapshot_price',
    ];
}
