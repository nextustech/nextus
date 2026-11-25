<?php

namespace App\Services;

use App\Exceptions\SubResellerLimitReachedException;
use App\Models\Reseller;
use App\Models\ResellerHierarchy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ResellerHierarchyService
{
    public function ensureSelfRow(Reseller $reseller): void
    {
        ResellerHierarchy::query()->updateOrCreate([
            'ancestor_reseller_id' => $reseller->id,
            'descendant_reseller_id' => $reseller->id,
        ], [
            'depth' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Attach a new child and write the ancestor closure rows.
     *
     * When a limit is supplied the child counter is incremented with a guarded
     * conditional UPDATE so concurrent invitations cannot exceed the parent's
     * sub-reseller allowance (doc 05 §4.2). A zero affected-row count aborts
     * the whole transaction.
     */
    public function attachChild(Reseller $parent, Reseller $child, ?int $maxChildren = null): void
    {
        DB::transaction(function () use ($parent, $child, $maxChildren): void {
            Reseller::withoutGlobalScopes()->whereKey($parent->id)->lockForUpdate()->firstOrFail();
            Reseller::withoutGlobalScopes()->whereKey($child->id)->lockForUpdate()->firstOrFail();

            $this->incrementChildCount($parent, $maxChildren);

            $child->update(['parent_reseller_id' => $parent->id]);

            $parentAncestors = ResellerHierarchy::query()
                ->where('descendant_reseller_id', $parent->id)
                ->get(['ancestor_reseller_id', 'depth']);

            $rows = [];
            foreach ($parentAncestors as $relation) {
                $rows[] = [
                    'ancestor_reseller_id' => $relation->ancestor_reseller_id,
                    'descendant_reseller_id' => $child->id,
                    'depth' => $relation->depth + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $this->insertRows($rows);
            $child->update([
                'path' => $this->buildPath($child),
            ]);
        });
    }

    public function reparent(Reseller $node, Reseller $newParent): void
    {
        if ($node->id === $newParent->id) {
            throw new InvalidArgumentException('A reseller cannot be reparented to itself.');
        }

        $nodeInTargetSubtree = ResellerHierarchy::query()
            ->where('ancestor_reseller_id', $node->id)
            ->where('descendant_reseller_id', $newParent->id)
            ->exists();

        if ($nodeInTargetSubtree) {
            throw new InvalidArgumentException('A reseller cannot be reparented below its own descendant.');
        }

        $lockIds = collect([$node->id, $newParent->id])->sort()->values();
        DB::transaction(function () use ($node, $newParent, $lockIds): void {
            foreach ($lockIds as $id) {
                Reseller::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->firstOrFail();
            }

            $cycleCheck = ResellerHierarchy::query()
                ->where('ancestor_reseller_id', $node->id)
                ->where('descendant_reseller_id', $newParent->id)
                ->exists();
            if ($cycleCheck) {
                throw new InvalidArgumentException('Reparenting would create a hierarchy cycle.');
            }

            $subtreeIds = ResellerHierarchy::query()
                ->where('ancestor_reseller_id', $node->id)
                ->pluck('descendant_reseller_id')
                ->all();

            ResellerHierarchy::query()
                ->whereIn('descendant_reseller_id', $subtreeIds)
                ->whereNotIn('ancestor_reseller_id', $subtreeIds)
                ->delete();

            $ancestorRows = ResellerHierarchy::query()
                ->where('descendant_reseller_id', $newParent->id)
                ->get(['ancestor_reseller_id', 'depth']);

            $rows = [];
            foreach ($ancestorRows as $ancestor) {
                foreach ($subtreeIds as $descendantId) {
                    $nodeDepth = ResellerHierarchy::query()
                        ->where('ancestor_reseller_id', $node->id)
                        ->where('descendant_reseller_id', $descendantId)
                        ->value('depth');
                    $depth = $ancestor->depth + 1 + (int) $nodeDepth;
                    $rows[] = [
                        'ancestor_reseller_id' => $ancestor->ancestor_reseller_id,
                        'descendant_reseller_id' => $descendantId,
                        'depth' => $depth,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            $this->insertRows($rows);

            $oldParentId = $node->parent_reseller_id;
            $node->update(['parent_reseller_id' => $newParent->id]);

            if ($oldParentId) {
                Reseller::withoutGlobalScopes()->whereKey($oldParentId)->decrement('child_count');
            }
            Reseller::withoutGlobalScopes()->whereKey($newParent->id)->increment('child_count');

            foreach ($subtreeIds as $descendantId) {
                $descendant = Reseller::withoutGlobalScopes()->find($descendantId);
                if ($descendant) {
                    $descendant->update(['path' => $this->buildPath($descendant)]);
                }
            }

            app(\App\Services\AuditLogger::class)->record(
                'tenant.lifecycle.changed',
                $node,
                [
                    'before' => ['parent_reseller_id' => $oldParentId],
                    'after' => ['parent_reseller_id' => $newParent->id],
                    'before_path' => $oldParentId ? '/'.$oldParentId.'/'.$node->id.'/' : '/'.$node->id.'/',
                    'after_path' => $this->buildPath($node),
                ],
                request(),
                $node->tenant_id,
            );
        });
    }

    public function subtree(Reseller $reseller): array
    {
        return Reseller::withoutGlobalScopes()
            ->join('reseller_hierarchy as h', 'h.descendant_reseller_id', '=', 'resellers.id')
            ->where('h.ancestor_reseller_id', $reseller->id)
            ->orderBy('h.depth')
            ->orderBy('resellers.id')
            ->get(['resellers.*', 'h.depth'])
            ->all();
    }

    public function depthOf(Reseller $reseller): int
    {
        return (int) (DB::table('reseller_hierarchy')
            ->where('descendant_reseller_id', $reseller->id)
            ->max('depth') ?? 0);
    }

    public function effectiveStatus(Reseller $reseller): string
    {
        if ($reseller->tenant->status !== 'active') {
            return $reseller->tenant->status === 'suspended' ? 'suspended' : $reseller->tenant->status;
        }

        $ancestorSuspended = DB::table('reseller_hierarchy')
            ->join('resellers as ancestors', 'ancestors.id', '=', 'reseller_hierarchy.ancestor_reseller_id')
            ->join('tenants', 'tenants.id', '=', 'ancestors.tenant_id')
            ->where('reseller_hierarchy.descendant_reseller_id', $reseller->id)
            ->where('reseller_hierarchy.depth', '>', 0)
            ->where('tenants.status', 'suspended')
            ->exists();

        return $ancestorSuspended ? 'suspended_ancestor' : 'active';
    }

    private function buildPath(Reseller $reseller): string
    {
        $ancestorIds = ResellerHierarchy::query()
            ->where('descendant_reseller_id', $reseller->id)
            ->orderByDesc('depth')
            ->pluck('ancestor_reseller_id')
            ->all();

        return count($ancestorIds) ? '/'.implode('/', $ancestorIds).'/' : '/'.$reseller->id.'/';
    }

    private function incrementChildCount(Reseller $parent, ?int $maxChildren): void
    {
        $query = Reseller::withoutGlobalScopes()->whereKey($parent->id);

        if ($maxChildren === null) {
            $query->increment('child_count');

            return;
        }

        if ($maxChildren < 0) {
            throw new InvalidArgumentException('Sub-reseller limit cannot be negative.');
        }

        $updated = (clone $query)
            ->where('child_count', '<', $maxChildren)
            ->increment('child_count');

        if ($updated === 0) {
            throw new SubResellerLimitReachedException($maxChildren);
        }
    }

    private function insertRows(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $batch) {
            if ($batch) {
                ResellerHierarchy::query()->insertOrIgnore($batch);
            }
        }
    }
}
