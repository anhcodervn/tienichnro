<?php

namespace App\Features\Client\GameImage\Requests;

use Illuminate\Validation\Rule;

class StoreGameImageCaptureLogsRequest extends ClaimGameImageCaptureRequest
{
    public function rules(): array
    {
        return [...parent::rules(),
            'logs' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'logs.*' => ['required', 'array:client_log_id,level,step,message,occurred_at'],
            'logs.*.client_log_id' => ['required', 'uuid', 'distinct:ignore_case'],
            'logs.*.level' => ['required', Rule::in(['debug', 'info', 'warning', 'error'])],
            'logs.*.step' => ['nullable', 'string', 'max:100'],
            'logs.*.message' => ['required', 'string', 'max:1000'],
            'logs.*.occurred_at' => ['nullable', 'date', 'after_or_equal:2000-01-01', 'before:2100-01-01'],
        ];
    }
}
