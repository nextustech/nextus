<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('users.view'), 403);

        return Inertia::render('Users/Index', [
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'status', 'last_login_at']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless($request->user()->can('users.update'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', 'string', 'max:5'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $user->update($validated);
        app(AuditLogger::class)->record(
            'user.updated',
            $user,
            ['before' => $user->getOriginal(), 'after' => $validated],
            $request,
        );

        return back();
    }

    public function deactivate(Request $request, User $user)
    {
        abort_unless($request->user()->can('users.deactivate'), 403);
        abort_if($request->user()->id === $user->id, 403);

        $user->update(['status' => 'disabled']);
        $user->tokens()->delete();
        app(AuditLogger::class)->record(
            'user.deactivated',
            $user,
            ['before' => ['status' => 'active'], 'after' => ['status' => 'disabled']],
            $request,
        );

        return back();
    }
}
