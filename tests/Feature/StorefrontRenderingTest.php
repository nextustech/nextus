<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\WebsiteMenu;
use App\Services\MediaLibraryService;
use App\Services\PageWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_keeps_published_content_and_route_live_while_a_new_draft_is_saved(): void
    {
        $tenant = $this->createTenant('storefront-live');
        $workflow = app(PageWorkflowService::class);
        $page = $workflow->create($tenant, [
            'title' => 'Published title',
            'slug' => 'original-page',
            'page_type' => 'about',
            'sections' => [
                ['type' => 'hero', 'settings' => ['headline' => 'Published headline']],
            ],
            'seo' => [
                'meta_title' => 'Published SEO title',
                'meta_description' => 'Published SEO description',
            ],
        ]);
        $workflow->publish($page);
        $workflow->update($page->fresh(), [
            'title' => 'Draft title',
            'slug' => 'draft-page',
            'sections' => [
                ['type' => 'hero', 'settings' => ['headline' => 'Draft headline']],
            ],
            'seo' => [
                'meta_title' => 'Draft SEO title',
                'meta_description' => 'Draft SEO description',
            ],
        ]);
        $noindex = $workflow->create($tenant, [
            'title' => 'Private search page',
            'slug' => 'hidden-from-sitemap',
            'page_type' => 'about',
            'seo' => ['robots' => 'noindex, nofollow'],
        ]);
        $workflow->publish($noindex);
        $header = WebsiteMenu::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'primary',
            'slug' => 'resources-menu',
            'status' => 'published',
            'position' => 99,
            'display_name' => 'Resources',
            'url' => '/original-page',
            'target_type' => 'page',
            'target' => 'original-page',
            'target_behavior' => '_self',
            'is_visible' => true,
        ]);
        WebsiteMenu::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'primary',
            'slug' => 'guides-menu',
            'status' => 'published',
            'position' => 1,
            'parent_id' => $header->id,
            'display_name' => 'Guides',
            'url' => '/original-page',
            'target_type' => 'page',
            'target' => 'original-page',
            'target_behavior' => '_self',
            'is_visible' => true,
        ]);

        $host = 'http://storefront-live.frenchies.com';
        $this->get($host.'/original-page')
            ->assertOk()
            ->assertSee('Published headline')
            ->assertSee('Published SEO title')
            ->assertSee('Published SEO description')
            ->assertDontSee('Draft headline')
            ->assertDontSee('Draft SEO title')
            ->assertSee('rel="canonical" href="'.$host.'/original-page"', false)
            ->assertSee('Resources')
            ->assertSee('Guides')
            ->assertSee('/theme.css', false);

        $this->get($host.'/draft-page')->assertStatus(404);
        $this->get($host.'/sitemap.xml')
            ->assertOk()
            ->assertSee('original-page')
            ->assertDontSee('hidden-from-sitemap')
            ->assertDontSee('draft-page');
        $this->get($host.'/theme.css')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=UTF-8')
            ->assertSee('--sf-brand-500');
        $this->get($host.'/robots.txt')->assertOk()->assertSee('/sitemap.xml');
    }

    public function test_expired_media_trash_is_purged_after_thirty_days(): void
    {
        $tenant = $this->createTenant('media-retention');
        config(['catalog.media.disk' => 'local']);
        Storage::fake('local');
        $path = sprintf('tenants/%d/media/expired.png', $tenant->id);
        Storage::disk('local')->put($path, 'old media');

        $media = \App\Models\MediaLibrary::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Expired',
            'path' => $path,
            'original_name' => 'expired.png',
            'mime_type' => 'image/png',
            'size' => '9',
            'type' => 'image',
            'is_public' => true,
            'alt_text' => 'Expired image',
        ]);
        $media->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

        $this->assertSame(1, app(MediaLibraryService::class)->purgeExpired());
        $this->assertDatabaseMissing('media_libraries', ['id' => $media->id]);
        Storage::disk('local')->assertMissing($path);
    }

    private function createTenant(string $slug): Tenant
    {
        return Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
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
