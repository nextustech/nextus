<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sub-reseller auto activation
    |--------------------------------------------------------------------------
    |
    | Parent-created sub-resellers are auto-activated when the creating parent
    | is healthy (active with no suspended ancestor). When disabled, or when
    | the parent is not healthy, the child stays pending for platform review.
    | A future wallet-balance check will be added with the money module.
    |
    */
    'auto_activate_sub_resellers' => env('RESELLER_AUTO_ACTIVATE_SUB_RESELLERS', true),

    /*
    | Maximum direct children a parent may create. Null means unlimited.
    | T2.7 will replace this scaffold with the active subscription plan limit.
    */
    'default_max_sub_resellers' => env('RESELLER_DEFAULT_MAX_SUB_RESELLERS', 10),

    'invitation_ttl_hours' => env('RESELLER_INVITATION_TTL_HOURS', 168),
];
