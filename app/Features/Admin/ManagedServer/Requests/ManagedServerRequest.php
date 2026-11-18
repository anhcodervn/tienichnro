<?php

namespace App\Features\Admin\ManagedServer\Requests;

use App\Exceptions\ApiException;
use App\Models\ManagedServer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManagedServerRequest extends FormRequest
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
            'server_id' => ['required', 'string', 'max:50', 'regex:/\A[A-Za-z0-9_-]+\z/', Rule::unique('managed_servers', 'server_id')
                ->where('type', $this->route('type'))->ignore($this->route('server') instanceof ManagedServer ? $this->route('server')->id : null)],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:0,4294967295'],
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
