<?php

namespace App\Features\Client\GamePassword\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GamePasswordHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->routeIs('admin.game-password.*') || ($this->user()?->role === 'admin' && $this->user()?->status === 'active');
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['pending', 'processing', 'success', 'error', 'needs_review'])], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
