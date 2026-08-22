<?php

namespace App\Features\Client\Profile\Actions;

use App\Actions\RecordUserLogAction;
use App\Models\User;
use Illuminate\Http\Request;

class RevokeApiTokenAction
{
    public function __construct(private readonly RecordUserLogAction $recordUserLogAction) {}

    public function handle(User $user, int $tokenId, Request $request): void
    {
        $token = $user->tokens()->whereKey($tokenId)->firstOrFail();
        $tokenName = $token->name;
        $token->delete();

        $this->recordUserLogAction->handle($user, 'api_token_revoked', "Thu hồi API key: {$tokenName}", $request);
    }
}
