<?php

namespace App\Features\Admin\Topup\Requests;

use App\Exceptions\ApiException;
use App\Models\GlobalTopupPackage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGlobalTopupPackageRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::unique(GlobalTopupPackage::class, 'code')],
            'denomination' => ['required', 'integer', 'min:1', 'max:999999999999', Rule::unique(GlobalTopupPackage::class, 'denomination')],
            'price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'original_price' => ['required', 'integer', 'min:1', 'max:999999999999', 'gte:price'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['required', 'integer', 'min:0'],
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
