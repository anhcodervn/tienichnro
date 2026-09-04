<?php

namespace App\Features\Admin\Affiliate\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAffiliatePackageRateRequest extends FormRequest
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
            'site_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'commission_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'fixed_amount' => ['nullable', 'integer', 'min:0', 'max:1000000000', 'required_if:commission_type,fixed'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:commission_type,percentage'],
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
