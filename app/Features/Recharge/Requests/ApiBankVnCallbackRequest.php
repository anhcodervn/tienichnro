<?php

namespace App\Features\Recharge\Requests;

use App\Exceptions\ApiException;
use App\Features\Recharge\Services\RechargeConfigurationService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ApiBankVnCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $secret = app(RechargeConfigurationService::class)->webhookSecret();

        return $secret !== '' && hash_equals($secret, (string) $this->header('X-Webhook-Secret', ''));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_keyword' => ['required', 'string', 'max:100'],
            'webhook_id' => ['required', 'integer', 'min:1'],
            'bank_id' => ['required', 'integer', 'min:1'],
            'bank_account_id' => ['required', 'integer', 'same:bank_id'],
            'sign' => ['required', 'string', 'regex:/\A[0-9a-fA-F]{32}\z/'],
            'payload.bank_account_id' => ['required', 'integer', 'same:bank_id'],
            'payload.transaction.id' => ['required', 'integer', 'min:1'],
            'payload.transaction.transaction_id' => ['required', 'string', 'max:200'],
            'payload.transaction.amount' => ['required', 'regex:/\A-?[0-9]{1,12}(?:\.0{1,2})?\z/'],
            'payload.transaction.description' => ['required', 'string', 'max:10000'],
            'payload.transaction.type' => ['required', 'in:credit,debit'],
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
