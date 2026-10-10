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
}
