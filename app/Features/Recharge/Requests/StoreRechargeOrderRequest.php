<?php

namespace App\Features\Recharge\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreRechargeOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active';
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');
        if (is_string($amount) && preg_match('/\A[0-9]{1,3}(?:\.[0-9]{3})+\z/', $amount)) {
            $this->merge(['amount' => str_replace('.', '', $amount)]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['amount' => ['required', 'integer', 'between:10000,50000000'], 'request_id' => ['required', 'uuid']];
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
