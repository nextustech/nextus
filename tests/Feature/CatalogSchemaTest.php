<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_tables_and_indexes_are_created(): void
    {
        foreach ([
            'categories',
            'products',
            'product_variants',
            'product_media',
            'product_packages',
            'package_items',
            'product_addons',
            'product_addon_links',
            'product_pricing',
            'tenant_products',
            'product_demos',
            'product_versions',
            'product_reviews',
            'product_documents',
            'demo_access_tokens',
            'demo_token_events',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), 'Missing catalog table: '.$table);
        }

        $this->assertTrue(Schema::hasColumns('tenant_products', [
            'tenant_id',
            'product_id',
            'grant_scope',
            'price_override',
            'status',
        ]));
    }

    public function test_canonical_categories_can_be_seeded(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertSame(15, Category::query()->count());
        $this->assertDatabaseHas('categories', ['slug' => 'real-estate-software']);
        $this->assertDatabaseHas('categories', ['slug' => 'ai-agents']);
    }

    public function test_product_owner_tenant_is_required_and_slug_is_owner_scoped(): void
    {
        $tenantA = $this->createTenant('catalog-a');
        $tenantB = $this->createTenant('catalog-b');
        $category = Category::query()->first();
        if (! $category) {
            $category = Category::query()->create([
                'name' => 'Test',
                'slug' => 'test',
                'position' => 1,
                'is_active' => true,
            ]);
        }

        app(TenantContext::class)->bind(TenantInfo::fromModel($tenantA));
        $productA = Product::query()->create([
            'public_id' => str_pad('PRODA', 26, 'X'),
            'owner_tenant_id' => $tenantA->id,
            'category_id' => $category->id,
            'product_type' => 'digital_service',
            'name' => 'Product A',
            'slug' => 'shared-slug',
            'fulfillment_model' => 'manual',
            'status' => 'draft',
        ]);

        app(TenantContext::class)->bind(TenantInfo::fromModel($tenantB));
        $productB = Product::query()->create([
            'public_id' => str_pad('PRODB', 26, 'X'),
            'owner_tenant_id' => $tenantB->id,
            'category_id' => $category->id,
            'product_type' => 'digital_service',
            'name' => 'Product B',
            'slug' => 'shared-slug',
            'fulfillment_model' => 'manual',
            'status' => 'draft',
        ]);

        $this->assertNotSame($productA->id, $productB->id);
        $this->assertSame('shared-slug', $productA->slug);
        $this->assertSame('shared-slug', $productB->slug);
    }

    private function createTenant(string $slug): Tenant
    {
        return Tenant::query()->create([
            'public_id' => str_pad(strtoupper($slug), 26, 'X'),
            'slug' => $slug,
            'name' => ucfirst($slug),
            'type' => 'reseller',
            'status' => 'active',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en-IN',
        ]);
    }
}
