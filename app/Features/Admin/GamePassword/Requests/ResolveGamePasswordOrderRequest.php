<?php

namespace App\Features\Admin\GamePassword\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveGamePasswordOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['success', 'error'])]];
    }
}
