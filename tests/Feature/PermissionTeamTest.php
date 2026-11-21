<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionTeamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['permission.teams' => true]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_catalogue_and_canonical_roles_are_seeded(): void
    {
        $this->assertSame(136, Permission::count());

        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $this->assertSame(12, Role::count());
        $this->assertSame(
            [
                'affiliate.partner',
                'customer.buyer',
                'platform.admin',
                'platform.finance_admin',
                'platform.product_manager',
                'platform.super_admin',
                'platform.support_agent',
                'platform.support_manager',
                'reseller.employee',
                'reseller.owner',
                'reseller.sales_agent',
                'reseller.sub_reseller',
            ],
            Role::query()->orderBy('name')->pluck('name')->all()
        );
    }

    public function test_role_assignment_does_not_cross_permission_teams(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(1);

        $user = User::factory()->create();
        $user->assignRole('platform.admin');

        $this->assertTrue($user->hasRole('platform.admin'));

        $registrar->setPermissionsTeamId(2);

        $this->assertFalse($user->fresh()->hasRole('platform.admin'));
    }
}
