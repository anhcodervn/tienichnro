<?php

namespace App\Features\Captcha\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SolveCaptchaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['image' => ['required', 'string', 'max:2800000']];
    }
}
