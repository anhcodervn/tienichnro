<?php

namespace App\Features\Admin\Topup\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['mark_paid', 'process', 'reorder', 'retry_provider_submission', 'sync_provider', 'complete', 'fail', 'cancel'])],
            'reason' => ['nullable', 'string', 'max:1000'],
            'force_reorder' => ['sometimes', 'boolean'],
        ];
    }
}
