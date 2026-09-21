<?php

namespace App\Features\Topup\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class RunProviderPriceCronRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expectedKey = trim((string) config('services.internal_cron.key'));
        $providedKey = trim((string) ($this->header('X-Cron-Key') ?: $this->bearerToken()));

        return $expectedKey !== ''
            && $providedKey !== ''
            && hash_equals($expectedKey, $providedKey);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
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
