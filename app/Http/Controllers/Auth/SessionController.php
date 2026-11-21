<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginActivity;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'appName' => config('app.name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $key = Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('Too many login attempts. Please try again later.'),
            ]);
        }

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $credentials['remember'] ?? false)) {
            RateLimiter::hit($key, 60);
            $this->recordActivity($request, null, $credentials['email'], 'failed');
            app(AuditLogger::class)->record(
                'auth.login.failed',
                null,
                ['email' => $credentials['email']],
                $request,
            );

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('tenant_id', $request->user()->tenant_id);
        RateLimiter::clear($key);
        $this->recordActivity($request, $request->user(), $credentials['email'], 'success');
        app(AuditLogger::class)->record(
            'auth.login.success',
            $request->user(),
            ['email' => $credentials['email']],
            $request,
        );

        return redirect()->intended('/dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        app(AuditLogger::class)->record(
            'auth.logout',
            $request->user(),
            [],
            $request,
        );
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function recordActivity(Request $request, ?User $user, string $email, string $status): void
    {
        LoginActivity::query()->create([
            'user_id' => $user?->id,
            'tenant_id' => $user?->tenant_id,
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => $status,
            'occurred_at' => now(),
        ]);
    }
}
