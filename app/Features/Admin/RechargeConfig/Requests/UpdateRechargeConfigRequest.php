<?php

namespace App\Features\Admin\RechargeConfig\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRechargeConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:manual,apibankvn_api'],
            'bank_name' => ['required', 'string', 'max:120'],
            'account_name' => ['required', 'string', 'max:120'],
            'account_number' => ['required', 'string', 'max:80'],
            'qr_template' => [
                'required',
                'string',
                'max:2000',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! str_contains((string) $value, '{nd}')) {
                        $fail('QR template bắt buộc phải chứa placeholder {nd} để đối soát nội dung chuyển khoản.');
                    }
                },
            ],
            'transfer_prefix' => ['required', 'string', 'max:4', 'regex:/^[A-Za-z0-9]+$/'],
            'api_base_url' => ['nullable', 'url', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:120'],
            'api_secret' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'api_bank_id' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider' => (string) $this->input('provider', 'manual'),
            'transfer_prefix' => strtoupper(trim((string) $this->input('transfer_prefix', ''))),
            'api_base_url' => 'https://apibankvn.com',
            'api_key' => filled($this->input('api_key'))
                ? trim((string) $this->input('api_key'))
                : null,
            'api_secret' => filled($this->input('api_secret'))
                ? trim((string) $this->input('api_secret'))
                : null,
            'webhook_secret' => filled($this->input('webhook_secret'))
                ? trim((string) $this->input('webhook_secret'))
                : null,
            'api_bank_id' => filled($this->input('api_bank_id'))
                ? (int) $this->input('api_bank_id')
                : null,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('provider') !== 'apibankvn_api') {
                return;
            }

            foreach ([
                'api_base_url' => 'URL API',
                'api_key' => 'API key',
                'api_secret' => 'API secret',
                'webhook_secret' => 'Webhook secret',
                'api_bank_id' => 'bank_id đối tác',
            ] as $field => $label) {
                if (! filled($this->input($field))) {
                    $validator->errors()->add($field, sprintf('%s là bắt buộc khi bật tích hợp apibankvn.com.', $label));
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'provider' => 'nhà cung cấp nạp tiền',
            'bank_name' => 'tên ngân hàng',
            'account_name' => 'chủ tài khoản',
            'account_number' => 'số tài khoản',
            'qr_template' => 'QR template',
            'transfer_prefix' => 'tiền tố nội dung nạp',
            'api_base_url' => 'URL API',
            'api_key' => 'API key',
            'api_secret' => 'API secret',
            'webhook_secret' => 'Webhook secret',
            'api_bank_id' => 'bank_id đối tác',
            'is_active' => 'trạng thái kích hoạt',
        ];
    }
}
