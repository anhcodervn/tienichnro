<?php

namespace App\Features\Client\Profile\Actions;

use App\Actions\RecordUserLogAction;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateApiKeyAction
{
    public function __construct(private readonly RecordUserLogAction $recordUserLogAction) {}

    /** @return array{api_key:string,api_secret:string} */
    public function handle(User $user, string $name, Request $request): array
    {
        $credentials = DB::transaction(function () use ($user, $name): array {
            User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->apiKeys()->where('key_type', 'topup')->where('status', 'active')->count() >= 10) {
                throw ValidationException::withMessages([
                    'name' => 'Bạn chỉ có thể tạo tối đa 10 API key. Hãy thu hồi key cũ trước.',
                ]);
            }

            $apiKey = $this->generateUniqueApiKey();
            $apiSecret = 'ncs_'.Str::random(64);

            $user->apiKeys()->create([
                'key_type' => 'topup',
                'name' => $name,
                'api_key' => $apiKey,
                'api_secret_hash' => Hash::make($apiSecret),
                'permissions' => ['balance:read', 'catalog:read', 'orders:create', 'orders:read'],
                'status' => 'active',
                'expired_at' => now()->addYear(),
            ]);

            return ['api_key' => $apiKey, 'api_secret' => $apiSecret];
        }, 3);

        $this->recordUserLogAction->handle($user, 'api_key_created', "Tạo API key: {$name}", $request);

        return $credentials;
    }

    private function generateUniqueApiKey(): string
    {
        do {
            $apiKey = 'nck_'.Str::lower(Str::random(40));
        } while (ApiKey::query()->where('api_key', $apiKey)->exists());

        return $apiKey;
    }
}
