<?php

namespace App\Features\Admin\Topup\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertGlobalTopupPackageLevelPriceRequest extends FormRequest
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
            'pricing_mode' => ['required', Rule::in(['discount', 'fixed'])],
            'discount_basis_points' => ['nullable', 'required_if:pricing_mode,discount', 'integer', 'min:0', 'max:10000'],
            'fixed_price' => ['nullable', 'required_if:pricing_mode,fixed', 'integer', 'min:0', 'max:999999999999'],
            'minimum_profit' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'is_active' => ['required', 'boolean'],
        ];
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
