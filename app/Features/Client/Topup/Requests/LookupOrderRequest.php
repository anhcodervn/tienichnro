<?php

namespace App\Features\Client\Topup\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LookupOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^TOP\d{6}[A-Z0-9]{6}$/i'],
            'email' => ['required', 'email:rfc', 'max:255'],
        ];
    }
}
