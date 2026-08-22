<?php

namespace App\Features\Admin\Topup\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'game_id' => ['required', Rule::exists('games', 'id')],
            'name' => ['required', 'string', 'max:255'], 'code' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['required', 'integer', 'min:0'], 'metadata' => ['nullable', 'array'],
        ];
    }
}
