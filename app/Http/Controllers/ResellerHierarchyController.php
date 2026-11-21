<?php

namespace App\Http\Controllers;

use App\Models\Reseller;
use App\Services\ResellerHierarchyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ResellerHierarchyController extends Controller
{
    public function __construct(private readonly ResellerHierarchyService $hierarchy) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('resellers.hierarchy_view'), 403);

        $roots = Reseller::withoutGlobalScopes()
            ->whereNull('parent_reseller_id')
            ->orderBy('name')
            ->get();

        return Inertia::render('Resellers/Hierarchy', [
            'roots' => $roots,
        ]);
    }

    public function subtree(Request $request, string $publicId): Response
    {
        abort_unless($request->user()->can('resellers.hierarchy_view'), 403);
        $node = Reseller::withoutGlobalScopes()->where('public_id', $publicId)->firstOrFail();

        return Inertia::render('Resellers/Hierarchy', [
            'roots' => [$node],
            'subtree' => $this->hierarchy->subtree($node),
            'node' => $node,
        ]);
    }

    public function reparent(Request $request, string $publicId)
    {
        abort_unless(
            $request->user()->can('resellers.hierarchy_manage')
            && $request->user()->can('hierarchy.transfer'),
            403,
        );

        $validated = $request->validate([
            'new_parent_public_id' => ['required', 'string'],
        ]);

        $node = Reseller::withoutGlobalScopes()->where('public_id', $publicId)->firstOrFail();
        $newParent = Reseller::withoutGlobalScopes()
            ->where('public_id', $validated['new_parent_public_id'])
            ->firstOrFail();

        try {
            $this->hierarchy->reparent($node, $newParent);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['hierarchy' => $exception->getMessage()]);
        }

        return back();
    }
}
