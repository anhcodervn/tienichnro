<?php

namespace App\Features\Admin\RechargeConfig\Requests;

use App\Exceptions\ApiException;
use App\Models\RechargeBonusTier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRechargeBonusTierRequest extends FormRequest
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
        $tier = $this->route('rechargeBonusTier');

        return [
            'minimum_amount' => [
                'required',
                'integer',
                'min:10000',
                'max:50000000',
                Rule::unique('recharge_bonus_tiers', 'minimum_amount')->ignore($tier instanceof RechargeBonusTier ? $tier->id : null),
            ],
            'bonus_percent' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'minimum_amount.unique' => 'Mốc số tiền này đã tồn tại.',
        ];
    }

    public function attributes(): array
    {
        return [
            'minimum_amount' => 'mốc nạp tối thiểu',
            'bonus_percent' => 'phần trăm khuyến mãi',
            'is_active' => 'trạng thái',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }
}
