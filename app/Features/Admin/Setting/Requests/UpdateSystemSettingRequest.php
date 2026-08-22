<?php

namespace App\Features\Admin\Setting\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:190'],
            'site_domain' => ['nullable', 'string', 'max:190'],
            'site_description' => ['nullable', 'string', 'max:2000'],
            'site_active' => ['required', 'boolean'],
            'allow_register' => ['required', 'boolean'],
            'light_logo' => ['nullable', 'string', 'max:2048'],
            'dark_logo' => ['nullable', 'string', 'max:2048'],
            'favicon' => ['nullable', 'string', 'max:2048'],
            'og_image' => ['nullable', 'string', 'max:2048'],
            'color_primary' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6})$/'],
            'color_accent' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6})$/'],
            'color_surface' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6})$/'],
            'support_email' => ['nullable', 'email', 'max:190'],
            'hotline' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'zalo' => ['nullable', 'string', 'max:255'],
            'youtube' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'robots' => ['nullable', 'string', 'max:100'],
            'gtm_id' => ['nullable', 'string', 'max:100'],
            'meta_pixel_id' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'site_name.required' => 'Vui lòng nhập tên hệ thống.',
            'support_email.email' => 'Email hỗ trợ không đúng định dạng.',
            'color_primary.regex' => 'Màu chính phải đúng mã HEX.',
            'color_accent.regex' => 'Màu nhấn phải đúng mã HEX.',
            'color_surface.regex' => 'Màu nền phải đúng mã HEX.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => $validator->errors()->first(),
            'data' => [
                'errors' => $validator->errors(),
            ],
        ], 422));
    }
}
