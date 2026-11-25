<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAddonLink extends Model
{
    protected $fillable = ['product_id', 'product_addon_id'];
}
