<?php

namespace App\Policies;

use App\Models\License;
use App\Models\User;

class LicensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, License $license): bool
    {
        return $user->role === 'admin';
    }

    public function transfer(User $user, License $license): bool
    {
        return $license->user_id === $user->id && $user->hasVerifiedEmail();
    }

    public function rotate(User $user, License $license): bool
    {
        return $license->user_id === $user->id && $license->status !== 'revoked';
    }

    public function download(User $user, License $license): bool
    {
        return $license->user_id === $user->id
            && in_array($license->status, ['unused', 'active'], true)
            && (! $license->expires_at || $license->expires_at->isFuture())
            && (bool) $license->product?->is_active;
    }
}
