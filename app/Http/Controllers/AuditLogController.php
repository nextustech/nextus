<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('audit.view'), 403);

        $validated = $request->validate([
            'actor' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:64'],
            'tenant_id' => ['nullable', 'integer'],
            'subject_type' => ['nullable', 'string', 'max:32'],
            'subject_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = AuditLog::query()
            ->where('tenant_id', app(\App\Tenancy\TenantContext::class)->id());

        if (isset($validated['actor'])) {
            $query->where('actor_id', $validated['actor']);
        }
        if (isset($validated['action'])) {
            $query->where('action', $validated['action']);
        }
        if (isset($validated['tenant_id']) && $request->user()->can('audit.view')) {
            $query->where('tenant_id', $validated['tenant_id']);
        }
        if (isset($validated['subject_type'])) {
            $query->where('subject_type', $validated['subject_type']);
        }
        if (isset($validated['subject_id'])) {
            $query->where('subject_id', $validated['subject_id']);
        }
        if (isset($validated['from'])) {
            $query->where('created_at', '>=', $validated['from']);
        }
        if (isset($validated['to'])) {
            $query->where('created_at', '<=', $validated['to'].' 23:59:59');
        }

        return Inertia::render('Audit/Index', [
            'logs' => $query
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(),
            'filters' => $validated,
        ]);
    }
}
