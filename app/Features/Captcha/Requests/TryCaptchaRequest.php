<?php

namespace App\Features\Captcha\Requests;

class TryCaptchaRequest extends SolveCaptchaRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [...parent::rules(), 'cf-turnstile-response' => ['required', 'string', 'max:2048']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...parent::messages(), 'cf-turnstile-response.required' => 'Vui lòng hoàn tất xác minh Cloudflare trước khi giải miễn phí.'];
    }
}
