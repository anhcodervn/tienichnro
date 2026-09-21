<?php

namespace App\Features\Admin\Topup\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SelectProviderPriceRequest extends FormRequest
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
            'provider_price' => ['required', 'integer', 'min:0', 'max:999999999999', 'lte:price'],
            'price' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'provider_price' => 'giá provider',
            'price' => 'giá bán',
        ];
    }
}
