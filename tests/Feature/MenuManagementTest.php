<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteMenu;
use App\Services\PageWorkflowService;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_links_can_be_nested_and_hidden_children_remain_manageable(): void
    {
        $tenant = $this->centralTenant();
        $manager = $this->makeManager($tenant);
        $page = app(PageWorkflowService::class)->create($tenant, [
            'title' => 'Documentation',
            'slug' => 'documentation',
            'page_type' => 'about',
        ]);
        app(PageWorkflowService::class)->publish($page);

        $this->actingAs($manager)->post('/cms/menus', [
            'name' => 'primary',
            'display_name' => 'Resources',
            'target_type' => 'page',
            'target' => 'documentation',
        ])->assertRedirect();
        $parent = WebsiteMenu::query()->where('display_name', 'Resources')->firstOrFail();

        $this->actingAs($manager)->post('/cms/menus', [
            'name' => 'primary',
            'display_name' => 'Guides',
            'target_type' => 'page',
            'target' => 'documentation',
            'parent_id' => $parent->id,
            'is_visible' => false,
        ])->assertRedirect();
        $child = WebsiteMenu::query()->where('display_name', 'Guides')->firstOrFail();

        $this->actingAs($manager)->get('/cms/menus')
            ->assertOk()
            ->assertInertia(fn ($inertia) => $inertia
                ->component('Cms/Menus')
                ->where('menus.0.id', $parent->id)
                ->where('menus.0.children.0.id', $child->id));
        $this->actingAs($manager)->delete('/cms/menus/'.$parent->id)
            ->assertSessionHasErrors('menu');
    }

    public function test_menu_rejects_http_external_links_and_prevents_cross_tenant_mutations(): void
    {
        $tenant = $this->centralTenant();
        $manager = $this->makeManager($tenant);

        $this->actingAs($manager)->from('/cms/menus')->post('/cms/menus', [
            'name' => 'primary',
            'display_name' => 'Unsafe',
            'target_type' => 'url',
            'target' => 'http://example.test',
        ])->assertSessionHasErrors('target');

        $otherTenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'slug' => 'other-menu-tenant',
            'name' => 'Other tenant',
            'type' => 'reseller',
            'status' => 'active',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en-IN',
        ]);
        $foreign = WebsiteMenu::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'primary',
            'slug' => 'foreign-item',
            'status' => 'published',
            'position' => 1,
            'display_name' => 'Foreign',
            'url' => '/foreign',
        ]);

        $this->actingAs($manager)->delete('/cms/menus/'.$foreign->id)->assertNotFound();
    }

    private function centralTenant(): Tenant
    {
        $tenant = Tenant::query()->where('slug', 'central')->firstOrFail();
        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        return $tenant;
    }

    private function makeManager(Tenant $tenant): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('cms.view', 'web');
        Permission::findOrCreate('cms.menus_manage', 'web');
        $role = Role::findOrCreate('cms.menu.management.test', 'web');
        $role->syncPermissions(['cms.view', 'cms.menus_manage']);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole($role);

        return $user;
    }
}
