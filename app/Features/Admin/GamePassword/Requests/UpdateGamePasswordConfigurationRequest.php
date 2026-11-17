<?php

namespace App\Features\Admin\GamePassword\Requests;

use App\Features\Client\GamePassword\Services\GamePasswordCaptchaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGamePasswordConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return [
            'price' => ['required', 'integer', 'between:0,100000000'],
            'captcha_provider' => ['sometimes', 'required', Rule::in(array_keys(GamePasswordCaptchaService::PROVIDERS))],
            'captcha_api_key' => ['nullable', 'string', 'max:255'],
            'clear_api_key' => ['sometimes', 'boolean'],
            'captcha_proxy' => ['nullable', 'string', 'max:512', 'regex:~^https?://[^\s/@:]+:[^\s/@]+@[^\s/]+:\d{1,5}$~'],
            'clear_proxy' => ['sometimes', 'boolean'],
        ];
    }
}
