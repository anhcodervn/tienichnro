<?php

namespace App\Features\Client\Profile\Actions;

use App\Actions\RecordUserLogAction;
use App\Models\User;
use Illuminate\Http\Request;

class RevokeApiKeyAction
{
    public function __construct(private readonly RecordUserLogAction $recordUserLogAction) {}

    public function handle(User $user, int $apiKeyId, Request $request): void
    {
        $apiKey = $user->apiKeys()
            ->where('key_type', 'topup')
            ->where('status', 'active')
            ->whereKey($apiKeyId)
            ->firstOrFail();
        $name = $apiKey->name;
        $apiKey->forceFill(['status' => 'revoked'])->save();

        $this->recordUserLogAction->handle($user, 'api_key_revoked', "Thu hồi API key: {$name}", $request);
    }
}
