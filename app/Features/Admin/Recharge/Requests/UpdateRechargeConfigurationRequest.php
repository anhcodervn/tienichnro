<?php

namespace App\Features\Admin\Recharge\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRechargeConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()?->status === 'active';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['enabled' => [$this->routeIs('admin.recharge.configure') ? 'required' : 'sometimes', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:500', 'required_with:api_secret'],
            'api_secret' => ['nullable', 'string', 'max:500', 'required_with:api_key'],
            'bank_id' => ['nullable', 'integer', 'min:1'], 'cron_key' => ['nullable', 'string', 'min:32', 'max:200'],
            'webhook_secret' => ['nullable', 'string', 'min:32', 'max:200']];
    }

    public function messages(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }
}
