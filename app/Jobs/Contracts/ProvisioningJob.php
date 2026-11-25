<?php

namespace App\Jobs\Contracts;

/**
 * Marks jobs that must run while their tenant is still pending.
 *
 * TenantQueueMiddleware normally releases jobs for non-active tenants.
 * Provisioning jobs are the exception: they are what makes the tenant active.
 */
interface ProvisioningJob {}
