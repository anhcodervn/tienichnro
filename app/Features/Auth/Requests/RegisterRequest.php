<?php

namespace App\Features\Auth\Requests;

use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->filled('name') && ! $this->filled('full_name')) {
            $payload['full_name'] = $this->string('name')->toString();
        }

        if ($this->filled('email')) {
            $payload['email'] = Str::lower(trim($this->string('email')->toString()));
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:32',
                'alpha_dash',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                        $fail('Tên đăng nhập không được có định dạng email.');
                    }
                },
                Rule::unique(User::class, 'username'),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique(User::class, 'phone'),
            ],
            'name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'full_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'password' => [
                'required',
                'confirmed',
                Rules\Password::defaults(),
            ],
            'accept_terms' => [
                'accepted',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Tên đăng nhập là bắt buộc.',
            'username.alpha_dash' => 'Tên đăng nhập chỉ được chứa chữ cái, số, dấu gạch ngang và gạch dưới.',
            'username.unique' => 'Tên đăng nhập đã được sử dụng.',
            'email.required' => 'Email là bắt buộc.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email đã được sử dụng.',
            'phone.unique' => 'Số điện thoại đã được sử dụng.',
            'name.max' => 'Họ và tên không được vượt quá 255 ký tự.',
            'password.required' => 'Mật khẩu là bắt buộc.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'accept_terms.accepted' => 'Bạn cần đồng ý với điều khoản sử dụng.',
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => 'tên đăng nhập',
            'email' => 'email',
            'phone' => 'số điện thoại',
            'name' => 'họ và tên',
            'full_name' => 'họ và tên',
            'password' => 'mật khẩu',
            'accept_terms' => 'điều khoản sử dụng',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if (! $this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new ApiException($validator->errors()->first(), 422, [
            'errors' => $validator->errors()->toArray(),
        ]);
    }
}
