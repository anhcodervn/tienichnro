<?php

namespace App\Features\Admin\GameImage\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageGameImageAccountRequest extends FormRequest
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
        $rules = ['capture_version' => ['required', 'integer', 'min:1']];
        if ($this->isMethod('DELETE')) {
            return $rules;
        }
        $account = $this->route('account');

        return [...$rules,
            'username' => ['required', 'string', 'max:100', Rule::unique('game_image_accounts')->ignore($account->id)->where(fn ($query) => $query->where('user_id', $account->user_id)->where('server', $this->input('server')))],
            'server' => ['required', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'max:200'],
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
