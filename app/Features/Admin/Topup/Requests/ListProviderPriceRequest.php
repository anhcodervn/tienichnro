<?php

namespace App\Features\Admin\Topup\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProviderPriceRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', Rule::in(['all', 'package', 'global'])],
            'provider_id' => ['nullable', 'integer', Rule::exists('topup_providers', 'id')],
        ];
    }
}
