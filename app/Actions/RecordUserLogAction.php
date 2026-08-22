<?php

namespace App\Actions;

use App\Jobs\SaveUserLogJob;
use App\Models\User;
use Illuminate\Http\Request;

class RecordUserLogAction
{
    public function handle(User $user, string $action, ?string $description, Request $request): void
    {
        SaveUserLogJob::dispatch(
            userId: $user->id,
            action: $action,
            description: $description,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        )->onQueue('user-logs')->afterCommit();
    }
}
