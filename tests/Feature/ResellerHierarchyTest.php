<?php

namespace Tests\Feature;

use App\Models\Reseller;
use App\Models\Tenant;
use App\Services\ResellerHierarchyService;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ResellerHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_attach_child_creates_self_and_ancestor_closure_rows(): void
    {
        $root = $this->createReseller('root');
        $child = $this->createReseller('child');
        $service = app(ResellerHierarchyService::class);

        $service->ensureSelfRow($root);
        $service->ensureSelfRow($child);
        $service->attachChild($root, $child);

        $this->assertDatabaseHas('reseller_hierarchy', [
            'ancestor_reseller_id' => $root->id,
            'descendant_reseller_id' => $child->id,
            'depth' => 1,
        ]);
        $this->assertSame('/'.$root->id.'/'.$child->id.'/', $child->fresh()->path);
        $this->assertSame(1, $root->fresh()->child_count);
    }

    public function test_reparent_moves_subtree_and_updates_depths(): void
    {
        $root = $this->createReseller('root');
        $child = $this->createReseller('child');
        $grandchild = $this->createReseller('grandchild');
        $newParent = $this->createReseller('new-parent');
        $service = app(ResellerHierarchyService::class);

        foreach ([$root, $child, $grandchild, $newParent] as $reseller) {
            $service->ensureSelfRow($reseller);
        }
        $service->attachChild($root, $child);
        $service->attachChild($child, $grandchild);
        $service->attachChild($root, $newParent);

        $service->reparent($child, $newParent);

        $this->assertDatabaseHas('reseller_hierarchy', [
            'ancestor_reseller_id' => $newParent->id,
            'descendant_reseller_id' => $child->id,
            'depth' => 1,
        ]);
        $this->assertDatabaseHas('reseller_hierarchy', [
            'ancestor_reseller_id' => $newParent->id,
            'descendant_reseller_id' => $grandchild->id,
            'depth' => 2,
        ]);
    }

    public function test_cycle_reparent_is_rejected(): void
    {
        $root = $this->createReseller('cycle-root');
        $child = $this->createReseller('cycle-child');
        $service = app(ResellerHierarchyService::class);
        $service->ensureSelfRow($root);
        $service->ensureSelfRow($child);
        $service->attachChild($root, $child);

        $this->expectException(InvalidArgumentException::class);
        $service->reparent($root, $child);
    }

    public function test_subtree_visibility_never_exposes_non_descendants(): void
    {
        $root = $this->createReseller('visible-root');
        $child = $this->createReseller('visible-child');
        $other = $this->createReseller('other');
        $service = app(ResellerHierarchyService::class);

        foreach ([$root, $child, $other] as $reseller) {
            $service->ensureSelfRow($reseller);
        }
        $service->attachChild($root, $child);

        $subtreeIds = collect($service->subtree($root))->pluck('id');
        $this->assertTrue($subtreeIds->contains($root->id));
        $this->assertTrue($subtreeIds->contains($child->id));
        $this->assertFalse($subtreeIds->contains($other->id));
    }

    private function createReseller(string $slug): Reseller
    {
        $tenant = Tenant::query()->create([
            'public_id' => str_pad(strtoupper($slug), 26, 'X'),
            'slug' => $slug,
            'name' => ucfirst($slug),
            'type' => 'reseller',
            'status' => 'active',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en-IN',
        ]);

        app(TenantContext::class)->bind(TenantInfo::fromModel($tenant));

        return Reseller::query()->create([
            'public_id' => str_pad(strtoupper($slug.'-profile'), 26, 'X'),
            'tenant_id' => $tenant->id,
            'name' => ucfirst($slug),
            'email' => $slug.'@example.test',
            'phone' => '+919999999999',
            'pan' => 'ABCDE1234F',
            'business_address' => 'Test address',
            'bank_proof_path' => 'kyc/test.pdf',
            'gstin_unregistered_declaration' => true,
            'aup_accepted_at' => now(),
            'dpdp_accepted_at' => now(),
            'kyc_status' => 'standard',
            'review_status' => 'approved',
            'kyc_submitted_at' => now(),
            'review_sla_due_at' => now()->addHours(24),
            'effective_status' => 'active',
        ]);
    }
}
