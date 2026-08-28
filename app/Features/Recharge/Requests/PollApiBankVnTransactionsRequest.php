<?php

namespace App\Features\Recharge\Requests;

use App\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class PollApiBankVnTransactionsRequest extends FormRequest
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
        return [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'force_refresh' => ['nullable', 'boolean'],
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

    public function startDate(): CarbonInterface
    {
        return CarbonImmutable::parse((string) ($this->validated('start_date') ?: now()->subDay()->toDateString()))->startOfDay();
    }

    public function endDate(): CarbonInterface
    {
        return CarbonImmutable::parse((string) ($this->validated('end_date') ?: now()->toDateString()))->endOfDay();
    }

    public function transactionLimit(): int
    {
        return (int) ($this->validated('limit') ?: 20);
    }

    public function shouldForceRefresh(): bool
    {
        return $this->boolean('force_refresh', true);
    }
}
