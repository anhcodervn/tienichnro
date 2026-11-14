<?php

namespace App\Features\Captcha\Requests;

use App\Models\CaptchaProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCaptchaProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('captcha_providers')->ignore($this->route('provider'))],
            'driver' => ['required', Rule::in(['nro_giaicaptcha', 'plusdino', 'phamgiang'])],
            'endpoint' => ['required', 'url:http,https', 'max:2048'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'timeout' => ['required', 'integer', 'min:1', 'max:30'],
            'is_enabled' => ['required', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:2048'],
            'clear_api_key' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $provider = $this->route('provider');
            $hasKey = $this->filled('api_key') || ($provider instanceof CaptchaProvider && filled($provider->api_key) && ! $this->boolean('clear_api_key'));
            if (in_array($this->input('driver'), ['plusdino', 'phamgiang'], true) && $this->boolean('is_enabled') && ! $hasKey) {
                $name = $this->input('driver') === 'phamgiang' ? 'api.phamgiang.net' : 'PlusDino';
                $validator->errors()->add('api_key', 'Nhập token '.$name.' trước khi bật nguồn.');
            }
            $parts = parse_url($this->string('endpoint')->toString());
            parse_str($parts['query'] ?? '', $query);
            if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || array_key_exists('token', $query)) {
                $validator->errors()->add('endpoint', 'URL không được chứa tài khoản, mật khẩu, fragment hoặc token. Nhập token trong ô khóa API riêng.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên nguồn.', 'name.unique' => 'Tên nguồn đã được sử dụng.',
            'endpoint.url' => 'Endpoint phải là URL HTTP hoặc HTTPS hợp lệ.',
            'timeout.max' => 'Timeout mỗi nguồn không được vượt quá 30 giây.',
        ];
    }
}
