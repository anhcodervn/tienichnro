<?php

namespace App\Features\Admin\User\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkSetUserDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['required', 'integer', 'distinct'],
            'scope' => ['required', Rule::in(['packages', 'global', 'all'])],
            'discount_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
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

    /** @return array{user_ids:array<int, int>,scope:string,discount_percent:float|int} */
    public function validated($key = null, $default = null): array
    {
        /** @var array{user_ids:array<int, int>,scope:string,discount_percent:float|int} $validated */
        $validated = parent::validated($key, $default);

        return $validated;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }
}
