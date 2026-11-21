<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\ResellerApplicationController;
use App\Http\Controllers\ResellerHierarchyController;
use App\Http\Controllers\SubResellerController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserInvitationController;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'appName' => config('app.name'),
    ]);
});

Route::get('/resellers/register', [ResellerApplicationController::class, 'create'])->name('resellers.register');
Route::post('/resellers/register', [ResellerApplicationController::class, 'store'])->name('resellers.store');
Route::get('/resellers/{publicId}/registered', [ResellerApplicationController::class, 'registered'])->name('resellers.registered');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::get('/invitations', [UserInvitationController::class, 'index'])->name('invitations.index');
    Route::post('/invitations', [UserInvitationController::class, 'store'])->name('invitations.store');
    Route::post('/invitations/{invitation}/revoke', [UserInvitationController::class, 'revoke'])->name('invitations.revoke');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/resellers', [ResellerApplicationController::class, 'index'])->name('resellers.index');
    Route::post('/resellers/{publicId}/approve', [ResellerApplicationController::class, 'approve'])->name('resellers.approve');
    Route::post('/resellers/{publicId}/request-info', [ResellerApplicationController::class, 'requestInfo'])->name('resellers.request-info');
    Route::post('/resellers/{publicId}/reject', [ResellerApplicationController::class, 'reject'])->name('resellers.reject');
    Route::get('/resellers/hierarchy', [ResellerHierarchyController::class, 'index'])->name('resellers.hierarchy');
    Route::get('/resellers/hierarchy/{publicId}', [ResellerHierarchyController::class, 'subtree'])->name('resellers.hierarchy.subtree');
    Route::post('/resellers/hierarchy/{publicId}/reparent', [ResellerHierarchyController::class, 'reparent'])->name('resellers.hierarchy.reparent');
    Route::get('/resellers/sub-resellers', [SubResellerController::class, 'index'])->name('resellers.sub-resellers.index');
    Route::get('/resellers/sub-resellers/create', [SubResellerController::class, 'create'])->name('resellers.sub-resellers.create');
    Route::post('/resellers/sub-resellers', [SubResellerController::class, 'store'])->name('resellers.sub-resellers.store');
    Route::post('/resellers/sub-resellers/{publicId}/resend', [SubResellerController::class, 'resend'])->name('resellers.sub-resellers.resend');
    Route::post('/resellers/sub-resellers/{publicId}/approve', [SubResellerController::class, 'approve'])->name('resellers.sub-resellers.approve');
    Route::post('/resellers/sub-resellers/{publicId}/reject', [SubResellerController::class, 'reject'])->name('resellers.sub-resellers.reject');
    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/{publicId}', [TenantController::class, 'show'])->name('tenants.show');
    Route::post('/tenants/{publicId}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/tenants/{publicId}/resume', [TenantController::class, 'resume'])->name('tenants.resume');
    Route::post('/tenants/{publicId}/terminate', [TenantController::class, 'terminate'])->name('tenants.terminate');
});

Route::get('/invitations/{token}/accept', [UserInvitationController::class, 'show'])->name('invitations.accept');
Route::post('/invitations/{token}/accept', [UserInvitationController::class, 'accept']);

Route::get('/sub-reseller-invitations/{token}/accept', [SubResellerController::class, 'showAccept'])->name('sub-reseller-invitations.accept');
Route::post('/sub-reseller-invitations/{token}/accept', [SubResellerController::class, 'accept']);
Route::get('/sub-reseller-invitations/{token}/accepted', [SubResellerController::class, 'accepted'])->name('sub-reseller-invitations.accepted');

Route::get('/health/queue', function () {
    try {
        Redis::ping();

        return response()->json(['status' => 'ok', 'redis' => 'connected']);
    } catch (\Throwable $exception) {
        return response()->json([
            'status' => 'degraded',
            'redis' => 'unavailable',
        ], 503);
    }
});
