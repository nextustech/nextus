<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'product_id', 'tenant_id', 'customer_id', 'order_item_id', 'rating',
        'title', 'body', 'status', 'edited_at', 'published_at', 'moderated_by',
    ];
}
