<?php

namespace App\Features\Client\GameImage\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageGameImageOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active' && $this->route('account')->user_id === $this->user()->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['capture_version' => ['required', 'integer', 'min:1'], 'action' => ['required', Rule::in(['cancel', 'pause', 'resume'])]];
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
