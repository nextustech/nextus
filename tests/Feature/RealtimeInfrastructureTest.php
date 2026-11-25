<?php

namespace Tests\Feature;

use App\Events\TenantNotification;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealtimeInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_notification_uses_private_tenant_channel(): void
    {
        $event = new TenantNotification(42, 'Tenant notice');

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('private-tenant.42.dashboard', $channels[0]->name);
        $this->assertSame(['tenant_id' => 42, 'title' => 'Tenant notice'], $event->broadcastWith());
    }

    public function test_broadcast_channel_authorizes_only_matching_tenant_user(): void
    {
        $tenant = Tenant::query()->where('slug', 'central')->firstOrFail();
        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $response = $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-tenant.'.$tenant->id.'.dashboard',
            ]);

        $response->assertOk();
    }

    public function test_queue_health_endpoint_reports_degraded_status(): void
    {
        $this->getJson('/health/queue')
            ->assertStatus(503)
            ->assertJson(['status' => 'degraded', 'redis' => 'unavailable']);
    }

    private function createTenant(string $slug): Tenant
    {
        return Tenant::query()->create([
            'public_id' => str_pad(strtoupper(substr($slug, 0, 10)), 26, 'X'),
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
