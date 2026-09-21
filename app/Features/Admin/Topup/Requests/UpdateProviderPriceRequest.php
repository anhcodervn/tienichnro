<?php

namespace App\Features\Admin\Topup\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderPriceRequest extends FormRequest
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
            'price' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'price' => 'giá bán',
        ];
    }
}
