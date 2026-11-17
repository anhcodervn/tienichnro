<?php

namespace App\Features\Client\GameVerification\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GameVerificationHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->routeIs('admin.game-verification.*') || ($this->user()?->role === 'admin' && $this->user()?->status === 'active');
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['pending', 'processing', 'success', 'error'])], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
