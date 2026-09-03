<?php

namespace App\Features\Tenant\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => strtolower(trim($this->string('slug')->toString())),
            'domain' => rtrim(strtolower(trim($this->string('domain')->toString())), '.'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['required', 'alpha_dash', 'max:80', Rule::unique('tenants', 'slug')],
            'domain' => ['required', 'string', 'max:190', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', Rule::unique('tenant_domains', 'domain')],
            'billing_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'allow_below_cost' => ['sometimes', 'boolean'],
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
        throw new ApiException($validator->errors()->first(), 422, ['errors' => $validator->errors()->toArray()]);
    }
}
