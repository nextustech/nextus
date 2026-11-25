<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPackage extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['product_id', 'name', 'fixed_price'];
}
