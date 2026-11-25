<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tenant.{tenantId}.dashboard', function ($user, int $tenantId) {
    return (int) $user->tenant_id === $tenantId;
});

Broadcast::channel('tenant.{tenantId}.user.{userId}', function ($user, int $tenantId, int $userId) {
    return (int) $user->tenant_id === $tenantId
        && (int) $user->id === $userId;
});
