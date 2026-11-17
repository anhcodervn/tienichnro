<?php

namespace App\Features\Captcha\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CaptchaHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'solves_search' => ['nullable', 'string', 'max:100'],
            'transactions_search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:processing,success,failed'],
            'source' => ['nullable', 'in:demo,api'],
            'direction' => ['nullable', 'in:credit,debit'],
            'solves_page' => ['nullable', 'integer', 'min:1'],
            'transactions_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
