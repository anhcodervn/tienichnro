<?php

namespace App\Features\Captcha\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseCaptchaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active';
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['tokens' => ['required', 'integer', 'min:1', 'max:10000000'], 'unit_price' => ['required', 'integer', 'min:1', 'max:1000000'], 'request_id' => ['required', 'uuid']];
    }
}
