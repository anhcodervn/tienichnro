<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}.wallet', function (User $user, int $userId): bool {
    return $user->id === $userId;
});

Broadcast::channel('users.{userId}.support', function (User $user, int $userId): bool {
    return $user->id === $userId;
});

Broadcast::channel('admin.support', function (User $user): bool {
    return $user->role === 'admin';
});
