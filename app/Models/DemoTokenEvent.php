<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DemoTokenEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'demo_access_token_id', 'tenant_id', 'event', 'duration_seconds',
        'payload', 'ip_address',
    ];
}
