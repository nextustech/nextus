<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
            // Permissions
    Permission::create(['name' => 'create project']);
    Permission::create(['name' => 'edit project']);
    Permission::create(['name' => 'delete project']);
    Permission::create(['name' => 'view project']);

    // Roles
    $owner = Role::create(['name' => 'owner']);
    $admin = Role::create(['name' => 'admin']);
    $sales = Role::create(['name' => 'sales']);
    $developer = Role::create(['name' => 'developer']);
    $customer = Role::create(['name' => 'customer']);

    // Assign permissions
    $owner->givePermissionTo(Permission::all());

    $admin->givePermissionTo([
    'create project',
    'view project',
    'edit project'
    ]);

    $sales->givePermissionTo([
        'create project',
        'view project'
    ]);

    $developer->givePermissionTo([
        'view project',
        'edit project'
    ]);
    
    $customer->givePermissionTo([
        'view project'
    ]);
    }
}
