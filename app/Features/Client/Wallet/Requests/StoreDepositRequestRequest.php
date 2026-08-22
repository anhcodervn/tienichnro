<?php

namespace App\Features\Client\Wallet\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepositRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:10000', 'max:50000000'],
            'config_id' => [
                'nullable',
                'integer',
                Rule::exists('config_recharge', 'id')->where('is_active', true),
            ],
        ];
    }
}
