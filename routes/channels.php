<?php

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}.wallet', function (User $user, int $userId): bool {
    return $user->id === $userId;
});

Broadcast::channel('users.{userId}.support', function (User $user, int $userId): bool {
    return $user->id === $userId;
});

Broadcast::channel('admin.sites.{tenantId}.support', function (User $user, int $tenantId): bool {
    return $user->role === 'admin'
        && (! app(TenantContext::class)->isActive() || $user->tenant_id === $tenantId);
});

Broadcast::channel('admin.sites.{tenantId}.topup.orders', function (User $user, int $tenantId): bool {
    return $user->role === 'admin'
        && (! app(TenantContext::class)->isActive() || $user->tenant_id === $tenantId);
});

Broadcast::channel('admin.platform.topup.orders', function (User $user): bool {
    $tenantContext = app(TenantContext::class);

    return $user->role === 'admin'
        && $tenantContext->isMain()
        && (! $tenantContext->isActive() || $user->tenant_id === $tenantContext->id());
});
