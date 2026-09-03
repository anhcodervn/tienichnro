<?php

namespace App\Features\Tenant\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantPackagePriceRequest extends FormRequest
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
            'pricing_mode' => ['required', Rule::in(['fixed', 'markup_amount', 'markup_percentage'])],
            'fixed_price' => ['nullable', 'integer', 'min:0', 'required_if:pricing_mode,fixed'],
            'markup_amount' => ['nullable', 'integer', 'min:0', 'required_if:pricing_mode,markup_amount'],
            'markup_percentage' => ['nullable', 'numeric', 'min:0', 'max:1000', 'required_if:pricing_mode,markup_percentage'],
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
