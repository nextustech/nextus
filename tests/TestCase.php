<?php

namespace Tests;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $central = Tenant::query()->where('slug', 'central')->first();
        if ($central) {
            app(TenantContext::class)->bind(TenantInfo::fromModel($central));
        }
    }
}
