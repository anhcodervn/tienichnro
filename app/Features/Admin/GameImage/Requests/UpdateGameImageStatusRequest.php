<?php

namespace App\Features\Admin\GameImage\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGameImageStatusRequest extends FormRequest
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
            'capture_version' => ['sometimes', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['processing', 'error'])],
            'error_code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.:-]+$/'],
            'error_message' => [Rule::requiredIf($this->input('status') === 'error'), 'nullable', 'string', 'max:1000'],
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
