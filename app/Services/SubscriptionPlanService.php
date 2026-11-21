<?php

namespace App\Services;

use App\Models\Reseller;

class SubscriptionPlanService
{
    /**
     * Maximum number of direct sub-resellers the reseller may create.
     *
     * T2.7 resolves this from the tenant's active subscription plan. Until the
     * plan tables exist, the configured default is used; platform-owned
     * reseller records are unlimited. Null always means unlimited.
     */
    public function planLimitFor(Reseller $reseller): ?int
    {
        if ($reseller->tenant?->type === 'platform') {
            return null;
        }

        $limit = config('resellers.default_max_sub_resellers');

        return $limit === null ? null : (int) $limit;
    }
}
