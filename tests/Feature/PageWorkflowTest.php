<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsitePage;
use App\Services\PageWorkflowService;
use App\Services\SectionCatalog;
use App\Services\StorefrontProvisioningService;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PageWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_catalog_ships_exactly_ten_typed_sections(): void
    {
        $types = SectionCatalog::types();

        $this->assertCount(10, $types);
        $this->assertSame(
            ['hero', 'feature_grid', 'pricing_table', 'testimonials', 'faq_accordion', 'cta', 'contact_form', 'blog_list', 'media', 'custom_html'],
            array_keys($types),
        );
        $this->assertTrue(SectionCatalog::isType('pricing_table'));
        $this->assertFalse(SectionCatalog::isType('grid_builder'));
    }

    public function test_create_saves_draft_with_first_revision(): void
    {
        $tenant = $this->createTenant('page-create');
        $page = app(PageWorkflowService::class)->create($tenant, [
            'title' => 'Spring Launch',
            'slug' => 'spring-launch',
            'page_type' => 'about',
            'sections' => [
                ['type' => 'hero', 'settings' => ['headline' => 'Hello']],
            ],
        ]);

        $this->assertSame('draft', $page->status);
        $this->assertNull($page->published_at);
        $this->assertNull($page->published_revision_id);
        $this->assertSame(1, $page->revisions()->count());
        $this->assertSame('Hello', $page->revisions()->first()->sections[0]['settings']['headline']);
        $this->assertNotEmpty($page->sections[0]['id']);
    }

    public function test_reserved_and_duplicate_slugs_are_blocked(): void
    {
        $tenant = $this->createTenant('page-slugs');
        $service = app(PageWorkflowService::class);

        $service->create($tenant, ['title' => 'Shop', 'slug' => 'shop-page', 'page_type' => 'about']);

        try {
            $service->create($tenant, ['title' => 'Other', 'slug' => 'checkout', 'page_type' => 'about']);
            $this->fail('Expected reserved slug to be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->errors());
            $this->assertStringContainsString('reserved', $e->errors()['slug'][0]);
        }

        $this->expectException(ValidationException::class);
        $service->create($tenant, ['title' => 'Dup', 'slug' => 'shop-page', 'page_type' => 'about']);
    }

    public function test_page_type_is_immutable_and_legal_creation_is_blocked(): void
    {
        $tenant = $this->createTenant('page-types');
        $service = app(PageWorkflowService::class);

        $page = $service->create($tenant, ['title' => 'About', 'slug' => 'about-page', 'page_type' => 'about']);

        // page_type is never part of the update contract (FR-CMS-023).
        $service->update($page, ['title' => 'About Us', 'page_type' => 'pricing']);
        $this->assertSame('about', $page->fresh()->page_type);

        try {
            $service->create($tenant, ['title' => 'Legal', 'slug' => 'legal-x', 'page_type' => 'legal']);
            $this->fail('Expected legal page creation to be blocked.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('page_type', $e->errors());
        }

        $this->expectException(ValidationException::class);
        $service->create($tenant, ['title' => '404', 'slug' => 'not-found-x', 'page_type' => 'not_found']);
    }

    public function test_publish_is_blocked_with_exact_section_and_field(): void
    {
        $tenant = $this->createTenant('page-publish-guard');
        $service = app(PageWorkflowService::class);

        $page = $service->create($tenant, [
            'title' => 'Hero Page',
            'slug' => 'hero-page',
            'page_type' => 'about',
            'sections' => [
                ['type' => 'hero', 'settings' => ['headline' => 'Valid']],
            ],
        ]);

        // Simulate an invalid section reaching the row (bypasses update validation).
        WebsitePage::query()->whereKey($page->id)->update([
            'sections' => [
                ['id' => (string) Str::ulid(), 'type' => 'hero', 'settings' => ['subheadline' => 'No headline']],
            ],
        ]);

        try {
            $service->publish($page->fresh());
            $this->fail('Expected publish to be blocked.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sections', $e->errors());
            $this->assertStringContainsString('Headline', $e->errors()['sections'][0]);
            $this->assertStringContainsString('hero', $e->errors()['sections'][0]);
        }

        $this->assertSame('draft', $page->fresh()->status);
    }

    public function test_publish_finalizes_revision_and_public_content(): void
    {
        $tenant = $this->createTenant('page-publish');
        $service = app(PageWorkflowService::class);

        $page = $service->create($tenant, [
            'title' => 'Launch',
            'slug' => 'launch',
            'page_type' => 'about',
            'sections' => [
                ['type' => 'cta', 'settings' => ['headline' => 'Buy now', 'cta_label' => 'Buy', 'cta_url' => 'https://example.test/buy']],
            ],
        ]);

        $service->publish($page);

        $page->refresh();
        $this->assertSame('published', $page->status);
        $this->assertNotNull($page->published_at);
        $this->assertNotNull($page->published_revision_id);
        $this->assertSame('Buy now', $page->publicSections()[0]['settings']['headline']);
    }

    public function test_edits_to_published_page_are_invisible_to_visitors(): void
    {
        $tenant = $this->createTenant('page-invisible');
        $service = app(PageWorkflowService::class);

        $page = $service->create($tenant, [
            'title' => 'Home Draft',
            'slug' => 'home-draft',
            'page_type' => 'about',
            'sections' => [
                ['type' => 'hero', 'settings' => ['headline' => 'Published headline']],
            ],
        ]);
        $service->publish($page);

        // Reseller edits the live page: status flips back to draft...
        $service->update($page->fresh(), [
            'title' => 'Home Draft v2',
            'sections' => [
                ['type' => 'hero', 'settings' => ['headline' => 'Unpublished headline']],
            ],
        ]);

        $page->refresh();
        $this->assertSame('draft', $page->status);
        $this->assertTrue($page->hasUnpublishedChanges());

        // ...but visitors keep serving the published revision.
        $this->assertSame('Published headline', $page->publicSections()[0]['settings']['headline']);
        $this->assertSame('Unpublished headline', $page->sections[0]['settings']['headline']);
    }

    public function test_revision_history_keeps_only_the_last_ten(): void
    {
        $tenant = $this->createTenant('page-revisions');
        $service = app(PageWorkflowService::class);

        $page = $service->create($tenant, ['title' => 'Rev', 'slug' => 'rev-page', 'page_type' => 'about']);

        for ($i = 0; $i < 14; $i++) {
            $service->update($page->fresh(), ['title' => 'Rev '.$i]);
        }

        $this->assertSame(10, $page->revisions()->count());
        $this->assertSame(15, (int) $page->revisions()->max('revision'));
    }

    public function test_restore_is_non_destructive_and_creates_new_draft(): void
    {
        $tenant = $this->createTenant('page-restore');
        $service = app(PageWorkflowService::class);

        $page = $service->create($tenant, [
            'title' => 'Original',
            'slug' => 'restore-page',
            'page_type' => 'about',
            'sections' => [
                ['type' => 'hero', 'settings' => ['headline' => 'Original headline']],
            ],
        ]);
        $originalRevision = $page->revisions()->first();

        $service->update($page->fresh(), [
            'title' => 'Changed',
            'sections' => [
                ['type' => 'hero', 'settings' => ['headline' => 'Changed headline']],
            ],
        ]);
        $service->publish($page->fresh());

        $service->restore($page->fresh(), $originalRevision->id);

        $page->refresh();
        $this->assertSame('draft', $page->status);
        $this->assertSame('Original', $page->title);
        $this->assertSame('Original headline', $page->sections[0]['settings']['headline']);

        // The original revision is untouched and a new draft revision was added.
        $originalRevision->refresh();
        $this->assertSame('Original headline', $originalRevision->sections[0]['settings']['headline']);
        $this->assertGreaterThan(2, $page->revisions()->count());
        $this->assertSame('draft', $page->status);
    }

    public function test_legal_pages_cannot_be_unpublished_or_deleted(): void
    {
        $tenant = $this->createTenant('page-legal');
        app(StorefrontProvisioningService::class)->provision($tenant);
        $service = app(PageWorkflowService::class);

        $terms = WebsitePage::query()->where('tenant_id', $tenant->id)->where('slug', 'terms')->firstOrFail();

        try {
            $service->unpublish($terms);
            $this->fail('Expected legal unpublish to be blocked.');
        } catch (ValidationException $e) {
            $this->assertSame('legal pages are required', $e->errors()['status'][0]);
        }
        $this->assertSame('published', $terms->fresh()->status);

        try {
            $service->delete($terms);
            $this->fail('Expected legal delete to be blocked.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('page', $e->errors());
        }
        $this->assertNull($terms->fresh()->deleted_at);

        // Content stays editable (AC-FR-CMS-030-1).
        $service->update($terms, [
            'sections' => [
                ['type' => 'custom_html', 'settings' => ['html' => '<p>Updated terms body.</p>']],
            ],
        ]);
        $this->assertSame('<p>Updated terms body.</p>', $terms->fresh()->sections[0]['settings']['html']);

        // Non-legal pages can be unpublished.
        $about = WebsitePage::query()->where('tenant_id', $tenant->id)->where('slug', 'about')->firstOrFail();
        $service->unpublish($about);
        $this->assertSame('draft', $about->fresh()->status);
        $this->assertNull($about->fresh()->published_revision_id);
    }

    public function test_404_page_is_a_singleton_and_cannot_be_deleted(): void
    {
        $tenant = $this->createTenant('page-404');
        app(StorefrontProvisioningService::class)->provision($tenant);
        $service = app(PageWorkflowService::class);

        $notFound = WebsitePage::query()->where('tenant_id', $tenant->id)->where('slug', '404')->firstOrFail();

        $this->expectException(ValidationException::class);
        $service->delete($notFound);
    }

    public function test_signed_preview_renders_draft_with_noindex_and_expires(): void
    {
        $tenant = $this->createTenant('page-preview');
        $service = app(PageWorkflowService::class);

        $page = $service->create($tenant, [
            'title' => 'Preview me',
            'slug' => 'preview-me',
            'page_type' => 'about',
            'sections' => [
                ['type' => 'hero', 'settings' => ['headline' => 'Secret draft headline']],
            ],
        ]);

        // Valid signed link renders unpublished content with noindex (AC-FR-CMS-028-1).
        $response = $this->get($service->previewUrl($page));
        $response->assertOk();
        $this->assertSame('noindex', $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('Secret draft headline', $response->getContent());
        $this->assertStringContainsString('noindex, nofollow', $response->getContent());

        // Tampered signature → 404.
        $this->get('/preview/pages/'.$page->id.'?signature=bogus')->assertNotFound();

        // After 7 days the link returns 404.
        $url = $service->previewUrl($page);
        Carbon::setTestNow(now()->addDays(8));
        try {
            $this->get($url)->assertNotFound();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_custom_html_sections_are_sanitized_with_warnings(): void
    {
        $tenant = $this->createTenant('page-sanitize');
        $service = app(PageWorkflowService::class);

        $page = $service->create($tenant, ['title' => 'HTML', 'slug' => 'html-page', 'page_type' => 'about']);

        $service->update($page, [
            'sections' => [
                ['type' => 'custom_html', 'settings' => ['html' => '<script>fetch(\'https://evil.example\')</script><p>Ok</p>']],
            ],
        ]);

        $stored = $page->fresh()->sections[0]['settings']['html'];
        $this->assertSame('<p>Ok</p>', $stored);
        $this->assertNotEmpty($service->lastWarnings);
        $this->assertStringContainsString('script', $service->lastWarnings[0]);
        $this->assertStringContainsString('policy 1.0', $service->lastWarnings[0]);
    }

    public function test_section_reorder_persists_new_order(): void
    {
        $tenant = $this->createTenant('page-reorder');
        $service = app(PageWorkflowService::class);

        $page = $service->create($tenant, [
            'title' => 'Reorder',
            'slug' => 'reorder-page',
            'page_type' => 'about',
            'sections' => [
                ['type' => 'hero', 'settings' => ['headline' => 'First']],
                ['type' => 'cta', 'settings' => ['headline' => 'Second', 'cta_label' => 'Go', 'cta_url' => 'https://example.test']],
                ['type' => 'media', 'settings' => ['image' => '/media/x.png', 'alt' => 'X']],
            ],
        ]);

        $ids = array_column($page->sections, 'id');
        $service->reorderSections($page, [$ids[1], $ids[2], $ids[0]]);

        $reordered = array_column($page->fresh()->sections, 'type');
        $this->assertSame(['cta', 'media', 'hero'], $reordered);

        // A mismatched id set is rejected.
        $this->expectException(ValidationException::class);
        $service->reorderSections($page->fresh(), [$ids[0]]);
    }

    public function test_pages_index_and_editor_require_permissions(): void
    {
        $viewer = $this->makeUserWithPermissions(['cms.view']);

        $this->actingAs($viewer)->get('/cms/pages')->assertOk();
        $this->actingAs($viewer)->get('/cms/pages/create')->assertForbidden();
        $this->actingAs($viewer)->post('/cms/pages', [
            'title' => 'X', 'slug' => 'x-page', 'page_type' => 'about',
        ])->assertForbidden();
    }

    public function test_page_create_publish_and_preview_flow_over_http(): void
    {
        $manager = $this->makeUserWithPermissions(['cms.view', 'cms.pages_manage']);
        $tenant = $this->centralTenant();

        $this->actingAs($manager)
            ->post('/cms/pages', [
                'title' => 'HTTP Page',
                'slug' => 'http-page',
                'page_type' => 'about',
                'sections' => [
                    ['type' => 'cta', 'settings' => ['headline' => 'Go', 'cta_label' => 'Go', 'cta_url' => 'https://example.test']],
                ],
            ])
            ->assertRedirect();

        $page = WebsitePage::query()->where('tenant_id', $tenant->id)->where('slug', 'http-page')->firstOrFail();
        $this->assertSame('draft', $page->status);

        $this->actingAs($manager)->post('/cms/pages/'.$page->id.'/publish')->assertRedirect();
        $this->assertSame('published', $page->fresh()->status);

        // A draft with an invalid section (simulated bad row) fails server-side
        // publish validation with the exact section + field.
        WebsitePage::query()->where('slug', 'http-page')->update([
            'sections' => [['type' => 'hero', 'settings' => ['subheadline' => 'missing headline']]],
        ]);
        $bad = $this->actingAs($manager)->post('/cms/pages/'.$page->id.'/publish');
        $bad->assertSessionHasErrors('sections');

        // Editor page renders with catalog + revisions + preview URL.
        $this->actingAs($manager)
            ->get('/cms/pages/'.$page->id.'/edit')
            ->assertOk()
            ->assertInertia(fn ($inertia) => $inertia
                ->component('Cms/PageEditor')
                ->where('page.slug', 'http-page')
                ->has('catalog', 10)
                ->has('revisions', 2)
                ->has('previewUrl'));
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

    private function centralTenant(): Tenant
    {
        return Tenant::query()->where('slug', 'central')->firstOrFail();
    }

    /** @param  list<string>  $permissions */
    private function makeUserWithPermissions(array $permissions): User
    {
        $central = $this->centralTenant();
        app(TenantContext::class)->bind(TenantInfo::fromModel($central));
        app(PermissionRegistrar::class)->setPermissionsTeamId($central->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate('page.workflow.test.'.($permissions[0] ?? 'none'), 'web');
        $role->syncPermissions($permissions);

        $user = User::factory()->create(['tenant_id' => $central->id]);
        $user->assignRole($role);

        return $user;
    }
}
