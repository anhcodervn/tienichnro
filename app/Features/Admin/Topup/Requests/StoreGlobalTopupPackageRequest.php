<?php

namespace App\Features\Admin\Topup\Requests;

use App\Exceptions\ApiException;
use App\Models\GlobalTopupPackage;
use App\Models\TopupProvider;
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
            'provider_id' => ['nullable', Rule::exists(TopupProvider::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::unique(GlobalTopupPackage::class, 'code')],
            'denomination' => ['required', 'integer', 'min:1', 'max:999999999999', Rule::unique(GlobalTopupPackage::class, 'denomination')],
            'carot_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'reward_x2_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'reward_x3_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'first_topup_reward_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'min_quantity' => ['prohibited'],
            'max_quantity' => ['prohibited'],
            'provider_price' => ['required', 'integer', 'min:0', 'max:999999999999', 'lte:price'],
            'price' => ['required', 'integer', 'min:0', 'max:999999999999', 'lte:denomination'],
            'description' => ['nullable', 'string'],
            'bonus_text' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['required', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
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
