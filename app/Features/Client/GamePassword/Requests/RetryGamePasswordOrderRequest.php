<?php

namespace App\Features\Client\GamePassword\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RetryGamePasswordOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $order = $this->route('order');

        return $user?->status === 'active' && ($this->routeIs('admin.game-password.*')
            ? $user->role === 'admin'
            : $order?->user_id === $user->id);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attempt_version' => ['required', 'integer', 'min:1'],
            'old_password' => ['prohibited'],
            'new_password' => ['prohibited'],
            'captcha_api_key' => ['nullable', 'required_with:captcha_api_secret', 'string', 'max:512', 'regex:/^[\x21-\x7E]+$/'],
            'captcha_api_secret' => ['nullable', 'required_with:captcha_api_key', 'string', 'max:512', 'regex:/^[\x21-\x7E]+$/'],
        ];
    }

    public function messages(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [];
    }
}
