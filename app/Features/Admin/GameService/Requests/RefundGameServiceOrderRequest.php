<?php

namespace App\Features\Admin\GameService\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundGameServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['cancelled', 'failed'])],
            'admin_note' => ['required', 'string', 'max:5000'],
        ];
    }
}
