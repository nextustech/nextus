<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'subscription_plan_id',
        'status',
        'billing_cycle',
        'snapshot_price',
        'snapshot_currency',
        'snapshot_plan',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'next_billing_date',
        'grace_ends_at',
        'cancel_at_period_end',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_price' => 'decimal:2',
            'snapshot_plan' => 'array',
            'cancel_at_period_end' => 'boolean',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'next_billing_date' => 'datetime',
            'grace_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }
}
