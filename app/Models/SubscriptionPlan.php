<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $attributes = [
        'features' => '[]',
        'quotas' => '{}',
    ];

    protected $fillable = [
        'public_id',
        'family',
        'name',
        'slug',
        'version',
        'billing_cycle',
        'price',
        'currency',
        'trial_days',
        'max_users',
        'max_products',
        'max_domains',
        'max_api_keys',
        'max_sub_resellers',
        'ai_token_cap',
        'features',
        'quotas',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'quotas' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
