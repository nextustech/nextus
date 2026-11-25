<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DemoAccessToken extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'product_demo_id', 'product_id', 'token', 'state',
        'lead_id', 'granted_at', 'expires_at', 'first_viewed_at',
        'last_viewed_at', 'converted_at',
    ];
}
