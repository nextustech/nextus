<?php

namespace Tests\Feature;

use App\Contracts\MediaScanner;
use App\Models\Tenant;
use App\Services\MediaLibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaLibraryAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_image_is_tenant_prefixed_and_served_only_to_its_tenant(): void
    {
        $tenant = $this->createTenant('media-owner');
        $this->createTenant('media-visitor');
        config(['catalog.media.disk' => 'local']);
        Storage::fake('local');
        $scanner = \Mockery::mock(MediaScanner::class);
        $scanner->shouldReceive('assertClean')->once();
        app()->instance(MediaScanner::class, $scanner);

        $media = app(MediaLibraryService::class)->upload(
            (int) $tenant->id,
            null,
            UploadedFile::fake()->image('catalog.png', 8, 8),
            'A sample product',
        );

        $this->assertStringStartsWith('tenants/'.$tenant->id.'/media/', $media->path);
        $this->assertSame('A sample product', $media->alt_text);
        Storage::disk('local')->assertExists($media->path);

        $this->get('http://media-owner.frenchies.com/media/'.$media->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->get('http://media-visitor.frenchies.com/media/'.$media->id)->assertNotFound();

    }

    public function test_image_upload_requires_alt_text(): void
    {
        $tenant = $this->createTenant('media-alt');

        try {
            app(MediaLibraryService::class)->upload(
                (int) $tenant->id,
                null,
                UploadedFile::fake()->image('missing-alt.png'),
                null,
            );
            $this->fail('Image upload without alt text should be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('alt_text', $exception->errors());
        }
    }

    public function test_svg_upload_removes_scripts_and_remote_styles(): void
    {
        $tenant = $this->createTenant('media-svg');
        config(['catalog.media.disk' => 'local']);
        Storage::fake('local');
        $scanner = \Mockery::mock(MediaScanner::class);
        $scanner->shouldReceive('assertClean')->once();
        app()->instance(MediaScanner::class, $scanner);

        $media = app(MediaLibraryService::class)->upload(
            (int) $tenant->id,
            null,
            UploadedFile::fake()->createWithContent(
                'brand.svg',
                '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><style>@import url(https://evil.test/x.css)</style><rect style="fill:url(https://evil.test/x)"/></svg>',
            ),
            'Brand mark',
        );
        $cleanSvg = Storage::disk('local')->get($media->path);

        $this->assertStringNotContainsString('<script', $cleanSvg);
        $this->assertStringNotContainsString('evil.test', $cleanSvg);
        $this->assertStringContainsString('<rect', $cleanSvg);
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
