<?php

namespace App\Features\Admin\User\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserGameServiceSecondaryPasswordRequest extends FormRequest
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
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Vui lòng nhập mật khẩu C2 cho CTV.',
        ];
    }

    public function attributes(): array
    {
        return [
            'password' => 'mật khẩu C2',
            'password_confirmation' => 'xác nhận mật khẩu C2',
        ];
    }

    /** @return array{password: string, password_confirmation: string} */
    public function validated($key = null, $default = null): array
    {
        /** @var array{password: string, password_confirmation: string} $validated */
        $validated = parent::validated($key, $default);

        return $validated;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }
}
