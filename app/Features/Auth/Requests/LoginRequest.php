<?php

namespace App\Features\Auth\Requests;

use App\Exceptions\ApiException;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
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
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'login' => trim($this->string('login')->toString()),
            'remember' => $this->boolean('remember'),
        ]);
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Email hoặc tên đăng nhập là bắt buộc.',
            'password.required' => 'Mật khẩu là bắt buộc.',
        ];
    }

    public function attributes(): array
    {
        return [
            'login' => 'email hoặc tên đăng nhập',
            'password' => 'mật khẩu',
            'remember' => 'ghi nhớ đăng nhập',
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->credentials(), $this->boolean('remember'))) {
            $this->hitRateLimit();

            if (! $this->expectsJson()) {
                throw ValidationException::withMessages(['login' => 'Thông tin đăng nhập không chính xác.']);
            }

            throw new ApiException('Thông tin đăng nhập không chính xác.', 422, [
                'errors' => [
                    'login' => ['Thông tin đăng nhập không chính xác.'],
                ],
            ]);
        }

        $this->clearRateLimit();
    }

    public function credentials(): array
    {
        $login = $this->normalizedLogin();

        return [
            $this->loginField() => $login,
            'password' => $this->string('password')->toString(),
        ];
    }

    public function loginField(): string
    {
        return filter_var($this->normalizedLogin(), FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    }

    public function normalizedLogin(): string
    {
        $login = trim($this->string('login')->toString());

        return filter_var($login, FILTER_VALIDATE_EMAIL) ? Str::lower($login) : $login;
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw new ApiException('Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau.', 429, [
            'errors' => [
                'login' => [trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => (int) ceil($seconds / 60),
                ])],
            ],
        ]);
    }

    public function hitRateLimit(): void
    {
        RateLimiter::hit($this->throttleKey());
    }

    public function clearRateLimit(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->normalizedLogin()).'|'.$this->ip());
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
