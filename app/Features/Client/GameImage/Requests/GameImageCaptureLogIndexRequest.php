<?php

namespace App\Features\Client\GameImage\Requests;

use App\Models\GameImageAccount;
use Illuminate\Foundation\Http\FormRequest;

class GameImageCaptureLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $account = $this->route('account');

        return $user?->status === 'active' && $account instanceof GameImageAccount
            && ($this->routeIs('api.game-image.logs.index') ? $user->role === 'admin' : $account->user_id === $user->id);
    }

    public function rules(): array
    {
        return ['capture_version' => ['sometimes', 'integer', 'min:1', 'max:'.$this->route('account')->capture_version],
            'after_id' => ['sometimes', 'integer', 'min:0'], 'per_page' => ['sometimes', 'integer', 'between:1,100']];
    }
}
