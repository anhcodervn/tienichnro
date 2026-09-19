<?php

namespace App\Features\Admin\Affiliate\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class AssignAffiliateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_code' => ['required', 'string', 'max:32', 'regex:/^TOP[A-Z0-9]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_code.required' => 'Hãy nhập mã đơn topup.',
            'order_code.regex' => 'Mã đơn topup phải bắt đầu bằng TOP.',
        ];
    }

    public function attributes(): array
    {
        return [];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'order_code' => mb_strtoupper(trim((string) $this->input('order_code'))),
        ]);
    }
}
