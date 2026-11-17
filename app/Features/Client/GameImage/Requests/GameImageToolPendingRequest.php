<?php

namespace App\Features\Client\GameImage\Requests;

use Illuminate\Validation\Rule;

class GameImageToolPendingRequest extends ClaimGameImageCaptureRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'status' => ['sometimes', Rule::in(['pending', 'processing'])],
        ];
    }
}
