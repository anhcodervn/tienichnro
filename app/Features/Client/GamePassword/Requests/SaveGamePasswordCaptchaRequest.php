<?php

namespace App\Features\Client\GamePassword\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveGamePasswordCaptchaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return $this->routeIs('tools.game-password.captcha.refresh') ? [] : [
            'captcha_api_key' => ['required', 'string', 'max:512', 'regex:/^[\x21-\x7E]+$/'],
            'captcha_api_secret' => ['required', 'string', 'max:512', 'regex:/^[\x21-\x7E]+$/'],
        ];
    }
}
