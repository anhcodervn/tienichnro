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

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'image.required' => 'Vui lòng gửi ảnh captcha base64 trong trường image.',
            'image.string' => 'Trường image phải là chuỗi ảnh captcha base64.',
            'image.max' => 'Ảnh captcha quá lớn. Vui lòng gửi ảnh tối đa 2MB.',
        ];
    }
}
