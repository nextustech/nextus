<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected $fillable = [
        'public_id',
        'slug',
        'name',
        'legal_name',
        'type',
        'status',
        'gstin',
        'currency',
        'timezone',
        'locale',
        'logo_path',
        'favicon_path',
    ];

    protected function casts(): array
    {
        return [
            'provisioned_at' => 'datetime',
            'suspended_at' => 'datetime',
            'terminated_at' => 'datetime',
        ];
    }

    public function reseller(): HasOne
    {
        return $this->hasOne(Reseller::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }
}
