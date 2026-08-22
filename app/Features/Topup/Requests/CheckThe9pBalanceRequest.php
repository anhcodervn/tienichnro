<?php

namespace App\Features\Topup\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckThe9pBalanceRequest extends FormRequest
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
}
