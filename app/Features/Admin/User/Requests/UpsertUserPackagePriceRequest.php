<?php

namespace App\Features\Admin\User\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertUserPackagePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pricing_mode' => ['required', Rule::in(['discount', 'fixed'])],
            'discount_percent' => ['nullable', 'required_if:pricing_mode,discount', 'numeric', 'min:0', 'max:100'],
            'fixed_price' => ['nullable', 'required_if:pricing_mode,fixed', 'integer', 'min:0'],
            'minimum_profit' => ['required', 'integer', 'min:0'],
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
        throw new ApiException($validator->errors()->first(), 422, ['errors' => $validator->errors()->toArray()]);
    }
}
