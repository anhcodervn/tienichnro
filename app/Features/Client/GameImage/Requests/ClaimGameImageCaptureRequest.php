<?php

namespace App\Features\Client\GameImage\Requests;

use App\Features\Client\GameImage\Services\GameImageCredentialService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ClaimGameImageCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return [
            'capture_version' => ['required', 'integer', 'min:1'],
            'claim_token' => ['required', 'uuid'],
            ...($this->routeIs('api.game-image.claim') ? ['public_key' => ['required', 'string', 'max:4096']] : []),
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->routeIs('api.game-image.claim') && $validator->errors()->isEmpty()) {
                app(GameImageCredentialService::class)->publicKey($this->string('public_key')->toString());
            }
        }];
    }
}
