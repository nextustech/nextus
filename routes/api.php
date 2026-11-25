<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::middleware(['tenant.host', 'tenant.token', 'tenant.reachable'])->group(function (): void {
    Route::post('/auth/token', function (Request $request) {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if ((int) $user->tenant_id !== app(\App\Tenancy\TenantContext::class)->id()) {
            abort(403, 'tenant_mismatch');
        }

        return response()->json([
            'token' => $user->createToken(
                $credentials['device_name'],
                ['tenant:'.$user->tenant_id],
            )->plainTextToken,
        ]);
    })->middleware('throttle:login');

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
});
