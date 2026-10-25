<?php

namespace App\Features\Client\Profile\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccountLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['product_code' => ['sometimes', 'string', 'max:64']];
    }
}
