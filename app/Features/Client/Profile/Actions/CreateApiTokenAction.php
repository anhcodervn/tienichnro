<?php

namespace App\Features\Client\Profile\Actions;

use App\Actions\RecordUserLogAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CreateApiTokenAction
{
    public function __construct(private readonly RecordUserLogAction $recordUserLogAction) {}

    public function handle(User $user, string $name, Request $request): string
    {
        if ($user->tokens()->count() >= 10) {
            throw ValidationException::withMessages([
                'name' => 'Bạn chỉ có thể tạo tối đa 10 API key. Hãy thu hồi key cũ trước.',
            ]);
        }

        $plainTextToken = $user->createToken($name, ['*'], now()->addYear())->plainTextToken;
        $this->recordUserLogAction->handle($user, 'api_token_created', "Tạo API key: {$name}", $request);

        return $plainTextToken;
    }
}
