<?php

namespace App\Features\Client\GameImage\Requests;

use Illuminate\Validation\Rule;

class UpdateGameImageToolStatusRequest extends ClaimGameImageCaptureRequest
{
    public function rules(): array
    {
        return [...parent::rules(),
            'status' => ['required', Rule::in(['pending', 'processing', 'error'])],
            'error_code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.:-]+$/'],
            'error_message' => [Rule::requiredIf($this->input('status') === 'error'), 'nullable', 'string', 'max:1000'],
        ];
    }
}
