<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVersion extends Model
{
    protected $fillable = [
        'product_id', 'version', 'changelog', 'content_snapshot',
        'content_storage_key', 'released_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'content_snapshot' => 'array',
            'released_at' => 'datetime',
        ];
    }
}
