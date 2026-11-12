<?php

namespace App\Features\Captcha\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CaptchaRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['rates' => ['required', 'array', 'min:1', 'max:30'], 'rates.*.min_tokens' => ['required', 'integer', 'min:1', 'max:10000000', 'distinct'], 'rates.0.min_tokens' => ['required', 'integer', 'in:1'], 'rates.*.unit_price' => ['required', 'integer', 'min:1', 'max:1000000']];
    }
}
