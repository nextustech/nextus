<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductDocument extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'product_id', 'title', 'path', 'mime_type',
        'size_bytes', 'visibility', 'position',
    ];
}
