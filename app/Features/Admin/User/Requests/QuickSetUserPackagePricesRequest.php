<?php

namespace App\Features\Admin\User\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuickSetUserPackagePricesRequest extends FormRequest
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
            'scope' => ['required', Rule::in(['packages', 'global'])],
            'package_ids' => ['required', 'array', 'min:1', 'max:500'],
            'package_ids.*' => ['required', 'integer', 'distinct'],
            'pricing_mode' => ['required', Rule::in(['discount', 'profit'])],
            'discount_percent' => ['nullable', 'required_if:pricing_mode,discount', 'numeric', 'min:0', 'max:100'],
            'profit_amount' => ['nullable', 'required_if:pricing_mode,profit', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422, ['errors' => $validator->errors()->toArray()]);
    }
}
