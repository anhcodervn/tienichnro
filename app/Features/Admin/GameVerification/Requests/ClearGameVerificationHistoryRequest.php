<?php

namespace App\Features\Admin\GameVerification\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ClearGameVerificationHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()?->status === 'active';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'older_than_days' => ['required_without:ids', 'prohibits:ids', 'integer', 'between:1,3650'],
            'ids' => ['required_without:older_than_days', 'prohibits:older_than_days', 'array', 'list', 'min:1', 'max:1000'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
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
