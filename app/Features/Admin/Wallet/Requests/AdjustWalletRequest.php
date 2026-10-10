<?php

namespace App\Features\Admin\Wallet\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdjustWalletRequest extends FormRequest
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
            'direction' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'description' => ['required', 'string', 'max:500'],
            'request_id' => ['required', 'uuid'],
        ];
    }
}
